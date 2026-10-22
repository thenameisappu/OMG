import { formatINR } from "@/lib/currency";
import { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useSearchParams } from 'react-router-dom';
import {
  Truck,
  Calendar,
  CheckCircle2,
  ArrowLeft,
  Moon,
  AlertCircle,
  CreditCard,
  ShieldCheck,
  Lock,
  Loader2,
  Check
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
import { load } from '@cashfreepayments/cashfree-js';

export default function Checkout() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { cart, cartTotal, clearCart } = useCart();
  const { user, loading, isAuthenticated } = useAuth();
  const { toast } = useToast();
  const [isSuccess, setIsSuccess] = useState(false);
  const [deliveryOption, setDeliveryOption] = useState('scheduled');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [paymentStep, setPaymentStep] = useState<string>('');
  const [orderId, setOrderId] = useState<string | null>(null);
  const [transactionId, setTransactionId] = useState<string | null>(null);
  const [confirmedDeliveryDate, setConfirmedDeliveryDate] = useState<string>('');
  const [isVerifyingRedirect, setIsVerifyingRedirect] = useState(false);
  const [verificationError, setVerificationError] = useState<string | null>(null);
  const [duplicateSubModal, setDuplicateSubModal] = useState<{
    isOpen: boolean;
    planId?: number;
    message?: string;
    formData?: any;
  } | null>(null);

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
      return slots.filter(slot => currentHour + 3 <= slot.cutoff + 3);
    }

    return slots;
  };

  const form = useForm({
    mode: 'onChange',
    defaultValues: {
      name: user?.name || '',
      email: user?.email || '',
      phone: user?.phone || '',
      houseNumber: '',
      street: '',
      address: user?.address || '',
      city: 'Bengaluru',
      pincode: '',
      deliveryOption: isAfter1PM ? 'scheduled' : 'same-day',
      deliveryDate: isAfter1PM ? tomorrowStr : today,
      deliveryTime: 'morning',
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
      form.setValue('city', 'Bengaluru');
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

  // Check if returning from Cashfree redirect with order_id in query params
  useEffect(() => {
    const hashQuery = window.location.hash.includes('?') 
      ? new URLSearchParams(window.location.hash.split('?')[1]) 
      : null;
    const windowQuery = new URLSearchParams(window.location.search);
    const redirectOrderId = searchParams.get('order_id') || hashQuery?.get('order_id') || windowQuery.get('order_id');

    if (!redirectOrderId) return;

    let isMounted = true;
    setIsVerifyingRedirect(true);
    setVerificationError(null);

    const verifyRedirect = async () => {
      try {
        const { data } = await orderService.verifyPayment(redirectOrderId);
        if (!isMounted) return;

        if (data && data.success && data.payment_status === 'paid') {
          setOrderId(redirectOrderId);
          setTransactionId(data.transaction_id || null);
          setIsSuccess(true);
          clearCart();
          toast({
            title: "Payment Confirmed",
            description: "Your payment was verified successfully by Cashfree.",
          });
        } else {
          setVerificationError(data?.message || "Payment verification pending or not confirmed.");
          toast({
            title: "Payment Status Pending",
            description: data?.message || "Payment verification could not be completed yet.",
            variant: "destructive",
          });
        }
      } catch (err: any) {
        if (!isMounted) return;
        setVerificationError(err?.message || "Failed to reach server for payment verification.");
      } finally {
        if (isMounted) {
          setIsVerifyingRedirect(false);
        }
      }
    };

    verifyRedirect();

    return () => {
      isMounted = false;
    };
  }, [searchParams, clearCart, toast]);

  const executeCashfreePayment = async (
    targetOrderId: string,
    paymentSessionId?: string,
    environment?: string,
    cashfreeActive?: boolean,
    selectedDate?: string
  ): Promise<boolean> => {
    setOrderId(targetOrderId);
    if (selectedDate) setConfirmedDeliveryDate(selectedDate);

    // If Cashfree credentials are configured on backend and session ID returned
    if (cashfreeActive && paymentSessionId) {
      setPaymentStep("Launching Cashfree Secure Gateway...");
      try {
        const mode = (environment === 'production' || environment === 'prod') ? 'production' : 'sandbox';
        const cashfree = await load({ mode });

        if (!cashfree) {
          throw new Error("Could not initialize Cashfree JS SDK.");
        }

        setPaymentStep("Complete payment in the Cashfree window...");

        // Open Cashfree checkout in modal
        await cashfree.checkout({
          paymentSessionId: paymentSessionId,
          redirectTarget: "_modal",
        });

        // After modal interaction (completed or closed), verify payment with backend
        setPaymentStep("Verifying payment confirmation with bank...");
        const verifyRes = await orderService.verifyPayment(targetOrderId);

        if (verifyRes.data && verifyRes.data.success && verifyRes.data.payment_status === 'paid') {
          setTransactionId(verifyRes.data.transaction_id || null);
          setIsSuccess(true);
          clearCart();
          toast({
            title: "Payment Received",
            description: "Your order and payment have been confirmed.",
          });
          return true;
        } else {
          toast({
            title: "Payment Incomplete",
            description: verifyRes.data?.message || "Payment was not completed. You can retry anytime.",
            variant: "destructive",
          });
          return false;
        }
      } catch (cfError: any) {
        console.error("Cashfree checkout error:", cfError);
        // Fallback verification in case user paid and closed window
        setPaymentStep("Checking payment status...");
        const verifyRes = await orderService.verifyPayment(targetOrderId);
        if (verifyRes.data && verifyRes.data.success && verifyRes.data.payment_status === 'paid') {
          setTransactionId(verifyRes.data.transaction_id || null);
          setIsSuccess(true);
          clearCart();
          toast({
            title: "Payment Received",
            description: "Your order has been confirmed.",
          });
          return true;
        }

        toast({
          title: "Payment Incomplete",
          description: cfError?.message || "Payment was not completed. You can try again anytime.",
          variant: "destructive",
        });
        return false;
      }
    } else {
      toast({
        title: "Payment Gateway Error",
        description: !cashfreeActive
          ? "Cashfree credentials are not configured on the backend server. Please verify CASHFREE_APP_ID and CASHFREE_SECRET_KEY in the server .env configuration."
          : "Cashfree payment session could not be established. Please try again.",
        variant: "destructive",
      });
      return false;
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
    setPaymentStep("Creating order...");
    try {
      // Prepare order payload matching backend expectations
      const orderPayload = {
        total_amount: cartTotal,
        customer_name: data.name,
        customer_email: data.email,
        customer_phone: data.phone,
        delivery_address: `${data.houseNumber}, ${data.street}, ${data.address}, ${data.city} - ${data.pincode}`,
        delivery_option: data.deliveryOption,
        delivery_date: data.deliveryDate || null,
        delivery_time: data.deliveryTime,
        payment_method: 'cashfree',
        items: cart.map(item => ({
          product_id: item.id,
          name: item.name,
          quantity: item.quantity,
          unit_price: item.price,
          _subscription: (item as any)._subscription || null
        }))
      };

      const { data: responseData, error } = await orderService.create(orderPayload);

      if (error) {
        throw new Error(error.response?.data?.message || "Failed to create order");
      }

      await executeCashfreePayment(
        responseData.id,
        responseData.payment_session_id,
        responseData.environment,
        responseData.cashfree_active,
        data.deliveryDate
      );

    } catch (error: any) {
      console.error("Order submission error:", error);
      console.log("Error details:", error.response);

      // Handle duplicate subscription protection gracefully
      if (error.response?.status === 409 && error.response?.data?.message === 'duplicate_subscription') {
        setDuplicateSubModal({
          isOpen: true,
          planId: error.response?.data?.plan_id,
          message: error.response?.data?.error || "You already have an active subscription for this plan.",
          formData: data
        });
        return;
      }

      toast({
        title: "Order Failed",
        description: error.response?.data?.message || error.message || "Something went wrong. Please check console.",
        variant: "destructive",
      });
    } finally {
      setIsSubmitting(false);
      setPaymentStep("");
    }
  };

  const handleConfirmDuplicateSubscription = async () => {
    if (!duplicateSubModal?.formData) return;
    const formData = duplicateSubModal.formData;
    setIsSubmitting(true);
    setPaymentStep("Creating subscription order...");
    try {
      const orderPayload = {
        total_amount: cartTotal,
        customer_name: formData.name,
        customer_email: formData.email,
        customer_phone: formData.phone,
        delivery_address: `${formData.houseNumber}, ${formData.street}, ${formData.address}, ${formData.city} - ${formData.pincode}`,
        delivery_option: formData.deliveryOption,
        delivery_date: formData.deliveryDate || null,
        delivery_time: formData.deliveryTime,
        payment_method: 'cashfree',
        items: cart.map(item => ({
          product_id: item.id,
          name: item.name,
          quantity: item.quantity,
          unit_price: item.price,
          _subscription: {
            ...((item as any)._subscription || {}),
            confirm_duplicate: true
          }
        }))
      };

      const { data: responseData, error } = await orderService.create(orderPayload);
      if (error) {
        throw new Error(error.response?.data?.message || "Failed to create order");
      }
      setDuplicateSubModal(null);

      await executeCashfreePayment(
        responseData.id,
        responseData.payment_session_id,
        responseData.environment,
        responseData.cashfree_active,
        formData.deliveryDate
      );
    } catch (error: any) {
      toast({
        title: "Order Failed",
        description: error.response?.data?.message || error.message || "Failed to place order.",
        variant: "destructive",
      });
    } finally {
      setIsSubmitting(false);
      setPaymentStep("");
    }
  };

  if (loading) {
    return <div className="min-h-screen flex items-center justify-center">Loading...</div>;
  }

  if (isVerifyingRedirect) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background px-4">
        <div className="bg-white border border-border/80 shadow-2xl rounded-3xl p-10 max-w-md w-full text-center space-y-6 animate-in fade-in zoom-in-95 duration-300">
          <div className="relative mx-auto w-20 h-20 flex items-center justify-center">
            <div className="absolute inset-0 rounded-full border-4 border-primary/20 border-t-primary animate-spin"></div>
            <CreditCard className="w-8 h-8 text-primary" />
          </div>
          <div>
            <h2 className="text-2xl font-serif-luxury font-bold text-primary mb-2">Verifying Payment</h2>
            <p className="text-sm text-muted-foreground leading-relaxed">
              We are connecting to Cashfree & your banking network to verify your transaction. Please do not refresh.
            </p>
          </div>
          <div className="inline-flex items-center gap-2 text-xs text-muted-foreground bg-muted/60 px-4 py-2 rounded-full">
            <ShieldCheck className="w-4 h-4 text-emerald-600" />
            <span>256-bit Bank-Grade Encryption</span>
          </div>
        </div>
      </div>
    );
  }

  if (verificationError && !isSuccess) {
    return (
      <div className="container py-24 text-center max-w-md mx-auto">
        <div className="inline-flex h-20 w-20 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 mb-6">
          <AlertCircle className="h-10 w-10" />
        </div>
        <h2 className="text-3xl font-bold text-primary font-serif-luxury italic mb-3">Payment Verification</h2>
        <p className="text-sm text-muted-foreground mb-8 leading-relaxed">
          {verificationError}
        </p>
        <div className="space-y-3">
          <Button
            onClick={() => {
              setVerificationError(null);
              window.location.hash = '#/checkout';
            }}
            className="w-full bg-primary text-white rounded-full h-12"
          >
            Return to Checkout
          </Button>
          <Button
            onClick={() => navigate('/orders')}
            variant="outline"
            className="w-full rounded-full h-12"
          >
            Check My Orders
          </Button>
        </div>
      </div>
    );
  }

  const hasRedirectOrderParam = !!(searchParams.get('order_id') || (window.location.hash.includes('order_id=')));
  if (cart.length === 0 && !isSuccess && !isVerifyingRedirect && !hasRedirectOrderParam) {
    setTimeout(() => navigate('/products'), 100); // Small delay to avoid flash if clearing cart
    return null;
  }

  if (isSuccess) {
    return (
      <div className="container py-24 text-center max-w-3xl mx-auto animate-in fade-in zoom-in-95 duration-300">
        <div className="inline-flex h-24 w-24 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 mb-6 shadow-inner">
          <CheckCircle2 className="h-12 w-12" />
        </div>
        <div className="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold mb-4">
          <ShieldCheck className="h-4 w-4" />
          <span>Payment Verified by Cashfree</span>
        </div>
        <h2 className="text-4xl font-bold text-primary mb-3 font-serif-luxury italic">Your Order is Confirmed!</h2>
        <p className="text-muted-foreground mb-8 max-w-md mx-auto text-sm leading-relaxed">
          Thank you for choosing OMG. Our floral artisans have received your order and payment, and are now handcrafting your luxury arrangement.
        </p>

        <div className="bg-white border border-border/80 shadow-xl rounded-2xl p-8 max-w-lg mx-auto mb-8 text-left space-y-4">
          <div className="flex items-center justify-between pb-4 border-b">
            <div>
              <span className="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Order Reference</span>
              <p className="text-lg font-bold text-primary font-mono">#{orderId ? orderId.substring(0, 8).toUpperCase() : 'CONFIRMED'}</p>
            </div>
            <div className="text-right">
              <span className="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Payment Status</span>
              <div>
                <span className="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                  PAID
                </span>
              </div>
            </div>
          </div>

          <div className="space-y-2.5 text-sm">
            {transactionId && (
              <div className="flex justify-between items-center text-xs">
                <span className="text-muted-foreground">Cashfree TXN ID:</span>
                <span className="font-mono font-medium text-foreground">{transactionId}</span>
              </div>
            )}
            <div className="flex justify-between">
              <span className="text-muted-foreground">Delivery Option:</span>
              <span className="font-medium capitalize">{deliveryOption.replace('-', ' ')}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Delivery Date:</span>
              <span className="font-medium">{confirmedDeliveryDate || form.getValues('deliveryDate') || 'As Scheduled'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Time Window:</span>
              <span className="font-medium capitalize">{form.getValues('deliveryTime') || 'Morning'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-muted-foreground">Payment Gateway:</span>
              <span className="font-medium text-primary">Cashfree PG (Instant)</span>
            </div>
          </div>
        </div>

        <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
          <Button 
            onClick={() => navigate('/orders')} 
            className="w-full sm:w-auto bg-primary text-white hover:bg-primary/90 rounded-full px-8 h-12 text-sm font-semibold"
          >
            Track in My Orders
          </Button>
          <Button 
            onClick={() => navigate('/')} 
            variant="outline"
            className="w-full sm:w-auto rounded-full px-8 h-12 text-sm font-semibold border-border hover:bg-muted"
          >
            Return to Gallery
          </Button>
        </div>
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
                    name="houseNumber"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>House / Flat Number</FormLabel>
                        <FormControl><Input placeholder="e.g. Flat 302 or House 18" {...field} className="h-12" required /></FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name="street"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Street</FormLabel>
                        <FormControl><Input placeholder="Street name" {...field} className="h-12" required /></FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </div>
                <FormField
                  control={form.control}
                  name="address"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Address / Area</FormLabel>
                      <FormControl><Input placeholder="Area, landmark, or locality" {...field} className="h-12" required /></FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <FormField
                    control={form.control}
                    name="city"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>City</FormLabel>
                        <FormControl><Input value="Bengaluru" readOnly {...field} className="h-12 bg-muted/30" /></FormControl>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                  <FormField
                    control={form.control}
                    name="pincode"
                    rules={{
                      required: 'Pincode is required',
                      validate: (value) => {
                        // Only validate when the full 6 digits are entered
                        if (value.length < 6) {
                          return true; // Don't show error while typing
                        }
                        // Check if it starts with 560 (Bengaluru)
                        if (!value.match(/^560\d{3}$/)) {
                          return 'Sorry, we are currently not delivering to your pincode. We currently deliver only within Bengaluru.';
                        }
                        return true;
                      },
                    }}
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Pincode</FormLabel>
                        <FormControl>
                          <Input
                            placeholder="e.g. 560001"
                            inputMode="numeric"
                            maxLength={6}
                            {...field}
                            onChange={(event) => field.onChange(event.target.value.replace(/\D/g, '').slice(0, 6))}
                            className="h-12"
                            required
                          />
                        </FormControl>
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

              {/* 3. Payment Method */}
              <section className="space-y-6">
                  <div className="flex items-center gap-3 mb-6">
                    <div className="h-8 w-8 rounded-full bg-primary text-white flex items-center justify-center font-bold">3</div>
                    <div>
                      <h3 className="text-xl font-bold text-primary">Payment Method</h3>
                      <p className="text-xs text-muted-foreground">Instant, encrypted checkout with Cashfree</p>
                    </div>
                  </div>

                  <div className="rounded-2xl border-2 border-primary/20 bg-gradient-to-br from-primary/[0.03] to-secondary/[0.05] p-6 space-y-4">
                    <div className="flex items-start justify-between gap-4">
                      <div className="flex items-center gap-3.5">
                        <div className="h-11 w-11 rounded-xl bg-primary text-white flex items-center justify-center shadow-md shrink-0">
                          <CreditCard className="h-5 w-5" />
                        </div>
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-primary text-base">Cashfree Payment Gateway</span>
                            <span className="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300">
                              Official Partner
                            </span>
                          </div>
                          <p className="text-xs text-muted-foreground mt-0.5">
                            UPI (GPay, PhonePe, Paytm), Credit & Debit Cards, NetBanking, and Wallets
                          </p>
                        </div>
                      </div>
                      <div className="flex items-center gap-2 shrink-0">
                        <span className="hidden sm:inline-flex text-[11px] font-semibold text-emerald-800 bg-emerald-100/80 px-2.5 py-0.5 rounded-full border border-emerald-300">
                          Active
                        </span>
                        <div className="h-6 w-6 rounded-full bg-primary text-white flex items-center justify-center shadow-sm" title="Selected Payment Option">
                          <Check className="h-3.5 w-3.5" />
                        </div>
                      </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 pt-2 border-t border-border/50 text-xs">
                      <span className="inline-flex items-center px-2.5 py-1 rounded-md bg-white border text-primary font-semibold text-[11px] shadow-sm">
                        UPI Instant
                      </span>
                      <span className="inline-flex items-center px-2.5 py-1 rounded-md bg-white border text-primary font-semibold text-[11px] shadow-sm">
                        Google Pay
                      </span>
                      <span className="inline-flex items-center px-2.5 py-1 rounded-md bg-white border text-primary font-semibold text-[11px] shadow-sm">
                        PhonePe
                      </span>
                      <span className="inline-flex items-center px-2.5 py-1 rounded-md bg-white border text-primary font-semibold text-[11px] shadow-sm">
                        Visa / MasterCard / RuPay
                      </span>
                      <span className="inline-flex items-center px-2.5 py-1 rounded-md bg-white border text-primary font-semibold text-[11px] shadow-sm">
                        NetBanking
                      </span>
                    </div>

                    <div className="flex items-center gap-2 text-xs text-muted-foreground pt-1">
                      <ShieldCheck className="h-4 w-4 text-emerald-600 shrink-0" />
                      <span>Zero extra transaction fees. Bank-grade 256-bit SSL encryption.</span>
                    </div>
                  </div>
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

                <Button 
                  type="submit" 
                  disabled={isSubmitting} 
                  className="w-full h-14 bg-primary text-white hover:bg-primary/90 rounded-full mt-10 text-base font-bold shadow-lg shadow-primary/20 flex items-center justify-center gap-2 transition-all cursor-pointer"
                >
                  {isSubmitting ? (
                    <>
                      <Loader2 className="h-5 w-5 animate-spin" />
                      <span className="text-sm font-semibold">{paymentStep || "Connecting Cashfree..."}</span>
                    </>
                  ) : (
                    <>
                      <Lock className="h-4 w-4" />
                      <span>Pay {formatINR(cartTotal)} via Cashfree</span>
                    </>
                  )}
                </Button>

                <div className="flex items-center justify-center gap-1.5 text-xs text-muted-foreground mt-4">
                  <ShieldCheck className="h-4 w-4 text-emerald-600 shrink-0" />
                  <span>Cashfree PCI-DSS Level 1 Certified Checkout</span>
                </div>

                <p className="text-center text-[11px] text-muted-foreground mt-4">
                  By clicking Pay, you agree to our Terms of Service & Privacy Policy.
                </p>
              </div>
            </div>
          </form>
        </Form>
      </div>

      {/* Duplicate Subscription Confirmation Modal */}
      {duplicateSubModal?.isOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-white border rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-left">
            <div className="flex items-center gap-3 mb-4">
              <div className="h-10 w-10 rounded-full bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                <AlertCircle className="h-5 w-5" />
              </div>
              <div>
                <h3 className="text-lg font-bold text-primary font-serif-luxury italic">Active Subscription Notice</h3>
                <p className="text-xs text-muted-foreground">Duplicate Subscription Confirmation</p>
              </div>
            </div>

            <p className="text-sm text-muted-foreground mb-6 leading-relaxed">
              {duplicateSubModal.message || "You already have an active subscription for this plan in your account."}
              <br /><br />
              Would you like to add an additional recurring delivery schedule for this plan (e.g., for another loved one or address), or review your existing subscriptions?
            </p>

            <div className="space-y-2.5">
              <Button
                type="button"
                onClick={handleConfirmDuplicateSubscription}
                disabled={isSubmitting}
                className="w-full bg-primary text-white hover:bg-primary/90 rounded-full h-11 font-medium text-sm"
              >
                {isSubmitting ? "Processing..." : "Yes, Add Another Subscription"}
              </Button>
              <Button
                type="button"
                variant="outline"
                onClick={() => {
                  setDuplicateSubModal(null);
                  navigate('/profile');
                }}
                className="w-full rounded-full h-11 border-border/80 hover:bg-muted text-sm font-medium"
              >
                Review Existing Subscriptions
              </Button>
              <Button
                type="button"
                variant="ghost"
                onClick={() => setDuplicateSubModal(null)}
                className="w-full rounded-full h-9 text-xs text-muted-foreground"
              >
                Cancel
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
