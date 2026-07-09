import { formatINR } from "@/lib/currency";
import { Link } from 'react-router-dom';
import { Trash2, Minus, Plus, ShoppingBag, ArrowLeft, Truck, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCart } from '@/contexts/CartContext';

export default function Cart() {
  const { cart, removeFromCart, updateQuantity, cartTotal } = useCart();

  if (cart.length === 0) {
    return (
      <div className="container py-32 text-center">
        <div className="inline-flex h-24 w-24 items-center justify-center rounded-full bg-accent mb-8">
          <ShoppingBag className="h-10 w-10 text-muted-foreground" />
        </div>
        <h2 className="text-3xl font-bold text-primary mb-4">Your Shopping Bag is Empty</h2>
        <p className="text-muted-foreground mb-10 max-w-md mx-auto">
          It looks like you haven't added any luxury arrangements yet. Let's find something beautiful!
        </p>
        <Link to="/products">
          <Button size="lg" variant="secondary" className="px-10 rounded-full">
            Start Shopping
          </Button>
        </Link>
      </div>
    );
  }

  return (
    <div className="bg-background min-h-screen py-16">
      <div className="container">
        <h1 className="text-4xl font-bold text-primary mb-10 font-serif-luxury italic">Your Shopping Bag</h1>
        
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-12">
          {/* Cart Items */}
          <div className="lg:col-span-2 space-y-8">
            <div className="space-y-6">
              {cart.map((item) => (
                <div key={item.id} className="flex gap-6 p-6 rounded-2xl bg-white luxury-shadow border border-primary/5 transition-all">
                  <div className="h-32 w-32 shrink-0 overflow-hidden rounded-xl bg-muted">
                    <img src={item.image} alt={item.name} className="h-full w-full object-cover" />
                  </div>
                  <div className="flex-1 flex flex-col">
                    <div className="flex justify-between mb-2">
                      <Link to={`/product/${item.slug}`} className="hover:text-secondary">
                        <h3 className="text-lg font-bold text-primary">{item.name}</h3>
                      </Link>
                      <button 
                        onClick={() => removeFromCart(item.id)}
                        className="text-muted-foreground hover:text-destructive transition-colors"
                      >
                        <Trash2 className="h-5 w-5" />
                      </button>
                    </div>
                    <p className="text-sm text-muted-foreground mb-4 line-clamp-1">{item.description}</p>
                    
                    <div className="mt-auto flex items-center justify-between">
                      <div className="flex items-center border border-input rounded-full px-3 py-1 bg-background">
                        <button 
                          onClick={() => updateQuantity(item.id, item.quantity - 1)}
                          className="p-1 hover:text-secondary"
                        >
                          <Minus className="h-3 w-3" />
                        </button>
                        <span className="w-8 text-center text-sm font-bold">{item.quantity}</span>
                        <button 
                          onClick={() => updateQuantity(item.id, item.quantity + 1)}
                          className="p-1 hover:text-secondary"
                        >
                          <Plus className="h-3 w-3" />
                        </button>
                      </div>
                      <span className="text-lg font-bold text-primary">{formatINR(item.price * item.quantity)}</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>

            <Link to="/products" className="inline-flex items-center gap-2 text-primary hover:text-secondary font-bold transition-colors">
              <ArrowLeft className="h-4 w-4" />
              Continue Shopping
            </Link>
          </div>

          {/* Summary */}
          <div className="lg:col-span-1">
            <div className="bg-primary text-white p-8 rounded-2xl sticky top-28">
              <h3 className="text-2xl font-bold mb-6">Order Summary</h3>
              
              <div className="space-y-4 mb-8">
                <div className="flex justify-between text-white/70">
                  <span>Subtotal</span>
                  <span>{formatINR(cartTotal)}</span>
                </div>
                <div className="flex justify-between text-white/70">
                  <span>Shipping</span>
                  <span className="text-secondary font-bold uppercase tracking-tighter">Free</span>
                </div>
                <div className="flex justify-between text-white/70">
                  <span>Estimated Tax</span>
                  <span>₹0</span>
                </div>
                <Separator className="bg-white/10" />
                <div className="flex justify-between text-xl font-bold">
                  <span>Total</span>
                  <span className="text-secondary">{formatINR(cartTotal)}</span>
                </div>
              </div>

              <Link to="/checkout">
                <Button variant="secondary" className="w-full h-14 text-lg font-bold rounded-full mb-6">
                  Proceed to Checkout
                </Button>
              </Link>

              <div className="space-y-4 pt-6 border-t border-white/10">
                <div className="flex items-center gap-3 text-xs text-white/60">
                  <Truck className="h-4 w-4 text-secondary" />
                  <span>Complimentary Luxury Packaging Included</span>
                </div>
                <div className="flex items-center gap-3 text-xs text-white/60">
                  <ShieldCheck className="h-4 w-4 text-secondary" />
                  <span>Secure SSL Encrypted Payment</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
