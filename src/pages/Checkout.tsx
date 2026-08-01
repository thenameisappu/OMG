import { formatINR } from "@/lib/currency";
import { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import {
  CreditCard,
  Truck,
  Calendar,
  CheckCircle2,
  ArrowLeft,
  Wallet,
  HandCoins,
  Moon
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { useCart } from '@/contexts/CartContext';
import { Separator } from '@/components/ui/separator';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { useAuth } from '@/contexts/AuthContext';
import { orderService } from '@/services/api';
import { useToast } from '@/hooks/use-toast';

export default function Checkout() {
  const navigate = useNavigate();
  const { cart, cartTotal, clearCart } = useCart();
  const { user, loading, isAuthenticated } = useAuth();
  const { toast } = useToast();
  const [isSuccess, setIsSuccess] = useState(false);
  const [deliveryOption, setDeliveryOption] = useState('scheduled');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [orderId, setOrderId] = useState<string | null>(null);

  // Get current date and time
  const now = new Date();
  const currentHour = now.getHours();
  const today = now.toISOString().split('T')[0];
  const tomorrow = new Date(now);
  tomorrow.setDate(tomorrow.getDate() + 1);
  const tomorrowStr = tomorrow.toISOString().split('T')[0];
  const dayAfterTomorrow = new Date(now);
  dayAfterTomorrow.setDate(dayAfterTomorrow.getDate() + 2);
  const dayAfterTomorrowStr = dayAfterTomorrow.toISOString().split('T')[0];

  const isAfter1PM = currentHour >= 13;
  const isAfter6PM = currentHour >= 18;

  const getTimeSlots = (date: string, option: string) => {
    if (option === 'midnight') return [{ value: 'midnight', label: 'Midnight Surprise (00:00)' }];

    const slots = [
      { value: 'morning', label: 'Morning (09:00 - 12:00)', cutoff: 6 },
      { value: 'afternoon', label: 'Afternoon (13:00 - 17:00)', cutoff: 10 },
      { value: 'evening', label: 'Evening (18:00 - 21:00)', cutoff: 15 },
    ];

    if (option === 'same-day' || date === today) {
      return slots.filter(slot => currentHour + 3 <= slot.cutoff + 3); // 3-hour cutoff rule
      // Wait, 3-hour cutoff usually means you can't book if start of slot is within 3 hours.
      // Let's refine:
      // Morning (9 AM) -> Cutoff 6 AM
      // Afternoon (1 PM) -> Cutoff 10 AM
      // Evening (6 PM) -> Cutoff 3 PM (15:00)
    }

    return slots;
  };

  const form = useForm({
    defaultValues: {
      name: user?.name || '',
      email: user?.email || '',
      phone: user?.phone || '',
      address: user?.address || '',
      city: user?.city || '',
      deliveryOption: isAfter1PM ? 'scheduled' : 'same-day',
      deliveryDate: isAfter1PM ? tomorrowStr : today,
      deliveryTime: 'morning',
      paymentMethod: 'card',
    },
  });

  // Redirect if not authenticated
  useEffect(() => {
    if (!loading && !isAuthenticated) {
      toast({
        title: "Authentication Required",
        description: "Please login to complete your purchase",
        variant: "destructive",
      });
      navigate('/login', { state: { from: '/checkout' } });
    }
  }, [loading, isAuthenticated, navigate, toast]);

  // Update form defaults when user data loads
  useEffect(() => {
    if (user) {
      form.setValue('name', user.name || '');
      form.setValue('email', user.email || '');
      form.setValue('phone', user.phone || '');
      form.setValue('address', user.address || '');
      form.setValue('city', user.city || '');
    }
  }, [user, form]);

  // Update time slot when delivery option changes
  const handleDeliveryOptionChange = (value: string) => {
    setDeliveryOption(value);
    if (value === 'midnight') {
      form.setValue('deliveryTime', 'midnight');
      form.setValue('deliveryDate', isAfter6PM ? dayAfterTomorrowStr : tomorrowStr);
    } else if (value === 'same-day') {
      form.setValue('deliveryDate', today);
      const availableSlots = getTimeSlots(today, 'same-day');
      if (availableSlots.length > 0) {
        form.setValue('deliveryTime', availableSlots[0].value);
      }
    } else {
      form.setValue('deliveryDate', tomorrowStr);
      form.setValue('deliveryTime', 'morning');
    }
  };

  const onSubmit = async (data: any) => {
    if (!user) {
      toast({
        title: "Error",
        description: "You must be logged in to place an order.",
        variant: "destructive",
      });
      return;
    }

    setIsSubmitting(true);
    try {
      // Prepare order payload matching backend expectations
      const orderPayload = {
        total_amount: cartTotal,
        customer_name: data.name,
        customer_email: data.email,
        customer_phone: data.phone,
        delivery_address: `${data.address}, ${data.city}`, // Combine address and city if needed, or just address
        delivery_option: data.deliveryOption,
        delivery_date: data.deliveryDate || null,
        delivery_time: data.deliveryTime,
        payment_method: data.paymentMethod,
        items: cart.map(item => ({
          product_id: item.id,
          quantity: item.quantity,
          unit_price: item.price
        }))
      };

      const { data: responseData, error } = await orderService.create(orderPayload);

      if (error) {
        throw new Error(error.response?.data?.message || "Failed to create order");
      }

      console.log('Order created:', responseData);
      setOrderId(responseData.id); // Assuming backend returns { id: "..." }
      setIsSuccess(true);
      clearCart();

      toast({
        title: "Order Placed Successfully",
        description: "Your order has been confirmed.",
      });

    } catch (error: any) {
      console.error("Order submission error:", error);
      console.log("Error details:", error.response); // Log full response for debugging
      toast({
        title: "Order Failed",
        description: error.response?.data?.message || error.message || "Something went wrong. Please check console.",
        variant: "destructive",
      });
    } finally {
      setIsSubmitting(false);
    }
  };

  if (loading) {
    return <div className="min-h-screen flex items-center justify-center">Loading...</div>;
  }

  if (cart.length === 0 && !isSuccess) {
    setTimeout(() => navigate('/products'), 100); // Small delay to avoid flash if clearing cart
    return null;
  }

  if (isSuccess) {
    return (
      <div className="container py-32 text-center">
        <div className="inline-flex h-24 w-24 items-center justify-center rounded-full bg-success/10 mb-8">
          <CheckCircle2 className="h-12 w-12 text-success" />
        </div>
        <h2 className="text-4xl font-bold text-primary mb-4 font-serif-luxury italic">Your Order is Confirmed!</h2>
        <p className="text-muted-foreground mb-10 max-w-md mx-auto">
          Thank you for choosing OMG. Our floral artisans are now preparing your luxury arrangement.
          A confirmation email has been sent to your inbox.
        </p>
        <div className="bg-muted/30 p-8 rounded-2xl max-w-lg mx-auto mb-10 text-left">
          <h4 className="font-bold mb-4">Order Details</h4>
          <div className="space-y-2 text-sm">
            <div className="flex justify-between"><span className="text-muted-foreground">Order ID:</span> <span className="font-medium">#{orderId ? orderId.substring(0, 8).toUpperCase() : 'PENDING'}</span></div>
            <div className="flex justify-between"><span className="text-muted-foreground">Delivery Date:</span> <span className="font-medium">{form.getValues('deliveryDate') || 'As Scheduled'}</span></div>
            <div className="flex justify-between"><span className="text-muted-foreground">Status:</span> <span className="text-secondary font-bold">Artisan Selection</span></div>
          </div>
        </div>
        <Button onClick={() => navigate('/')} className="bg-primary text-white hover:bg-primary/90 rounded-full px-10 h-12">
          Return to Gallery
        </Button>
      </div>
    );
  }

  return (
    <div className="bg-background min-h-screen py-16">
      <div className="container">
        <div className="flex items-center gap-4 mb-10">
          <Button variant="ghost" size="icon" onClick={() => navigate('/cart')}>
            <ArrowLeft className="h-5 w-5" />
          </Button>
          <h1 className="text-4xl font-bold text-primary font-serif-luxury italic">Secured Checkout</h1>
        </div>

        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="grid grid-cols-1 lg:grid-cols-12 gap-12">
            {/* Form Sections */}
            <div className="lg:col-span-7 space-y-12">
              {/* Personal Details */}
              <section className="space-y-6">
                <div className="flex items-center gap-3 mb-6">
                  <div className="h-8 w-8 rounded-full bg-primary text-white flex items-center justify-center font-bold">1</div>
                  <h3 className="text-xl font-bold text-primary">Personal Details</h3>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <FormField
                    control={form.control}
                    name="name"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Full Name</FormLabel>
                        <FormControl><Input placeholder="Enter your name" {...field} className="h-12" required /></FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name="email"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Email Address</FormLabel>
                        <FormControl><Input placeholder="Enter your email" type="email" {...field} className="h-12" required /></FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </div>
                <FormField
                  control={form.control}
                  name="phone"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Mobile Number</FormLabel>
                      <FormControl><Input placeholder="Enter your mobile number" {...field} className="h-12" required /></FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </section>

              {/* Delivery Details */}
              <section className="space-y-6">
                <div className="flex items-center gap-3 mb-6">
                  <div className="h-8 w-8 rounded-full bg-primary text-white flex items-center justify-center font-bold">2</div>
                  <h3 className="text-xl font-bold text-primary">Delivery Information</h3>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <FormField
                    control={form.control}
                    name="address"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Delivery Address</FormLabel>
                        <FormControl><Input placeholder="Street Address" {...field} className="h-12" required /></FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name="city"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>City</FormLabel>
                        <FormControl><Input placeholder="City" {...field} className="h-12" required /></FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </div>

                <div className="space-y-4">
                  <Label>Delivery Frequency</Label>
                  <FormField
                    control={form.control}
                    name="deliveryOption"
                    render={({ field }) => (
                      <FormItem>
                        <FormControl>
                          <RadioGroup
                            onValueChange={(value) => {
                              field.onChange(value);
                              handleDeliveryOptionChange(value);
                            }}
                            defaultValue={field.value}
                            className="grid grid-cols-1 md:grid-cols-3 gap-4"
                          >
                            <Label
                              className={`flex items-center justify-between p-4 rounded-xl border-2 cursor-pointer transition-all ${field.value === 'scheduled' ? 'border-secondary bg-secondary/5' : 'border-muted'
                                }`}
                            >
                              <div className="flex items-center gap-3">
                                <RadioGroupItem value="scheduled" />
                                <div className="space-y-1">
                                  <p className="font-bold">Scheduled Delivery</p>
                                  <p className="text-xs text-muted-foreground">Select a future date</p>
                                </div>
                              </div>
                              <Calendar className="h-5 w-5 text-muted-foreground" />
                            </Label>
                            <Label
                              className={`flex items-center justify-between p-4 rounded-xl border-2 transition-all ${isAfter1PM ? 'opacity-50 cursor-not-allowed border-muted' : (field.value === 'same-day' ? 'border-secondary bg-secondary/5 cursor-pointer' : 'border-muted cursor-pointer')
                                }`}
                            >
                              <div className="flex items-center gap-3">
                                <RadioGroupItem value="same-day" disabled={isAfter1PM} />
                                <div className="space-y-1">
                                  <p className="font-bold">Same Day Delivery</p>
                                  <p className="text-xs text-muted-foreground">{isAfter1PM ? 'Closed for today' : 'Premium Priority'}</p>
                                </div>
                              </div>
                              <Truck className="h-5 w-5 text-muted-foreground" />
                            </Label>
                            <Label
                              className={`flex items-center justify-between p-4 rounded-xl border-2 cursor-pointer transition-all ${field.value === 'midnight' ? 'border-secondary bg-secondary/5' : 'border-muted'
                                }`}
                            >
                              <div className="flex items-center gap-3">
                                <RadioGroupItem value="midnight" />
                                <div className="space-y-1">
                                  <p className="font-bold">Midnight Surprise</p>
                                  <p className="text-xs text-muted-foreground">12 AM Delivery</p>
                                </div>
                              </div>
                              <Moon className="h-5 w-5 text-muted-foreground" />
                            </Label>
                          </RadioGroup>
                        </FormControl>
                      </FormItem>
                    )}
                  />
                </div>

                {deliveryOption !== 'same-day' && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <FormField
                      control={form.control}
                      name="deliveryDate"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>Preferred Date</FormLabel>
                          <FormControl>
                            <Input
                              type="date"
                              {...field}
                              min={deliveryOption === 'midnight' ? (isAfter6PM ? dayAfterTomorrowStr : tomorrowStr) : tomorrowStr}
                              className="h-12"
                              required
                            />
                          </FormControl>
                        </FormItem>
                      )}
                    />
                    <FormField
                      control={form.control}
                      name="deliveryTime"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>Time Slot</FormLabel>
                          <FormControl>
                            {deliveryOption === 'midnight' ? (
                              <div className="w-full h-12 rounded-md border border-input bg-muted px-3 flex items-center text-sm">
                                <Moon className="h-4 w-4 mr-2 text-primary" />
                                <span className="font-semibold">Midnight Surprise (00:00)</span>
                              </div>
                            ) : (
                              <select {...field} className="w-full h-12 rounded-md border border-input bg-background px-3 text-sm focus:ring-1 focus:ring-secondary outline-none">
                                {getTimeSlots(form.getValues('deliveryDate'), deliveryOption).map(slot => (
                                  <option key={slot.value} value={slot.value}>{slot.label}</option>
                                ))}
                              </select>
                            )}
                          </FormControl>
                        </FormItem>
                      )}
                    />
                  </div>
                )}

                {deliveryOption === 'same-day' && (
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-2">
                      <Label>Delivery Date</Label>
                      <div className="w-full h-12 rounded-md border border-input bg-muted px-3 flex items-center text-sm">
                        <Calendar className="h-4 w-4 mr-2 text-primary" />
                        <span className="font-semibold">Today ({today})</span>
                      </div>
                    </div>
                    <FormField
                      control={form.control}
                      name="deliveryTime"
                      render={({ field }) => (
                        <FormItem>
                          <FormLabel>Available Time Slot</FormLabel>
                          <FormControl>
                            <select {...field} className="w-full h-12 rounded-md border border-input bg-background px-3 text-sm focus:ring-1 focus:ring-secondary outline-none">
                              {getTimeSlots(today, 'same-day').map(slot => (
                                <option key={slot.value} value={slot.value}>{slot.label}</option>
                              ))}
                            </select>
                          </FormControl>
                        </FormItem>
                      )}
                    />
                  </div>
                )}
              </section>

              {/* Payment Method */}
              <section className="space-y-6">
                <div className="flex items-center gap-3 mb-6">
                  <div className="h-8 w-8 rounded-full bg-primary text-white flex items-center justify-center font-bold">3</div>
                  <h3 className="text-xl font-bold text-primary">Payment Method</h3>
                </div>
                <FormField
                  control={form.control}
                  name="paymentMethod"
                  render={({ field }) => (
                    <FormItem>
                      <FormControl>
                        <RadioGroup
                          onValueChange={field.onChange}
                          defaultValue={field.value}
                          className="grid grid-cols-1 md:grid-cols-3 gap-4"
                        >
                          <Label className={`flex flex-col items-center gap-2 p-6 rounded-xl border-2 cursor-pointer transition-all ${field.value === 'card' ? 'border-secondary bg-secondary/5' : 'border-muted'}`}>
                            <RadioGroupItem value="card" className="sr-only" />
                            <CreditCard className="h-8 w-8 text-primary" />
                            <span className="font-bold text-sm">Card</span>
                          </Label>
                          <Label className={`flex flex-col items-center gap-2 p-6 rounded-xl border-2 cursor-pointer transition-all ${field.value === 'wallet' ? 'border-secondary bg-secondary/5' : 'border-muted'}`}>
                            <RadioGroupItem value="wallet" className="sr-only" />
                            <Wallet className="h-8 w-8 text-primary" />
                            <span className="font-bold text-sm">Wallet</span>
                          </Label>
                          <Label className={`flex flex-col items-center gap-2 p-6 rounded-xl border-2 cursor-pointer transition-all ${field.value === 'cod' ? 'border-secondary bg-secondary/5' : 'border-muted'}`}>
                            <RadioGroupItem value="cod" className="sr-only" />
                            <HandCoins className="h-8 w-8 text-primary" />
                            <span className="font-bold text-sm">COD</span>
                          </Label>
                        </RadioGroup>
                      </FormControl>
                    </FormItem>
                  )}
                />
              </section>
            </div>

            {/* Sidebar Summary */}
            <div className="lg:col-span-5">
              <div className="bg-white luxury-shadow rounded-2xl p-8 border sticky top-28">
                <h3 className="text-xl font-bold mb-8">Order Overview</h3>
                <div className="space-y-6 max-h-[400px] overflow-y-auto mb-8 pr-2">
                  {cart.map((item) => (
                    <div key={item.id} className="flex gap-4">
                      <div className="h-20 w-20 rounded-lg overflow-hidden shrink-0">
                        <img src={item.image} alt={item.name} className="h-full w-full object-cover" />
                      </div>
                      <div className="flex-1 flex flex-col justify-center">
                        <h4 className="font-bold text-sm text-primary leading-tight">{item.name}</h4>
                        <p className="text-xs text-muted-foreground mt-1">Qty: {item.quantity}</p>
                        <p className="text-sm font-bold text-secondary mt-1">{formatINR(item.price * item.quantity)}</p>
                      </div>
                    </div>
                  ))}
                </div>

                <div className="space-y-4 border-t pt-6">
                  <div className="flex justify-between text-muted-foreground">
                    <span>Subtotal</span>
                    <span>{formatINR(cartTotal)}</span>
                  </div>
                  <div className="flex justify-between text-muted-foreground">
                    <span>Shipping</span>
                    <span className="text-secondary font-bold uppercase text-xs tracking-wider">Free</span>
                  </div>
                  <Separator />
                  <div className="flex justify-between text-2xl font-bold text-primary">
                    <span>Total</span>
                    <span>{formatINR(cartTotal)}</span>
                  </div>
                </div>

                <Button type="submit" disabled={isSubmitting} className="w-full h-14 bg-primary text-white hover:bg-primary/90 rounded-full mt-10 text-lg font-bold">
                  {isSubmitting ? 'Processing...' : 'Complete Purchase'}
                </Button>

                <p className="text-center text-xs text-muted-foreground mt-6">
                  By completing your purchase, you agree to our Terms of Service and Privacy Policy.
                </p>
              </div>
            </div>
          </form>
        </Form>
      </div>
    </div>
  );
}
