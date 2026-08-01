import { formatINR } from "@/lib/currency";
import { Link } from 'react-router-dom';
import { Trash2, Minus, Plus, ShoppingBag, ArrowLeft, ShieldCheck, Gift, CheckCircle2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useCart } from '@/contexts/CartContext';
import { useState } from 'react';

export default function Cart() {
  const { cart, removeFromCart, updateQuantity, cartTotal } = useCart();


  if (cart.length === 0) {
    return (
      <div className="container py-28 text-center max-w-lg mx-auto">
        <div className="inline-flex h-24 w-24 items-center justify-center rounded-full bg-secondary/10 border border-secondary/20 mb-8 gold-glow">
          <ShoppingBag className="h-10 w-10 text-secondary" />
        </div>
        <h2 className="text-3xl font-bold font-serif text-primary mb-3">Your Shopping Bag is Empty</h2>
        <p className="text-muted-foreground mb-8 text-sm leading-relaxed">
          It looks like you haven't added any luxury floral arrangements yet. Let's find something extraordinary!
        </p>
        <Link to="/products">
          <Button size="lg" variant="secondary" className="px-10 rounded-full font-bold shadow-lg gold-glow">
            Explore Collections
          </Button>
        </Link>
      </div>
    );
  }

  return (
    <div className="bg-white min-h-screen py-12">
      <div className="container max-w-6xl">
        {/* Step Progress Tracker */}
        <div className="flex justify-center items-center gap-4 md:gap-12 mb-12 border-b border-border pb-8 text-xs font-bold uppercase tracking-wider">
          <div className="flex items-center gap-2 text-secondary">
            <span className="w-6 h-6 rounded-full bg-secondary text-primary flex items-center justify-center font-extrabold text-[10px]">1</span>
            <span>Shopping Bag</span>
          </div>
          <div className="h-0.5 w-12 bg-border hidden sm:block" />
          <div className="flex items-center gap-2 text-muted-foreground">
            <span className="w-6 h-6 rounded-full bg-muted text-muted-foreground flex items-center justify-center font-extrabold text-[10px]">2</span>
            <span>Delivery Info</span>
          </div>
          <div className="h-0.5 w-12 bg-border hidden sm:block" />
          <div className="flex items-center gap-2 text-muted-foreground">
            <span className="w-6 h-6 rounded-full bg-muted text-muted-foreground flex items-center justify-center font-extrabold text-[10px]">3</span>
            <span>Payment</span>
          </div>
        </div>

        <h1 className="text-3xl md:text-4xl font-bold text-primary mb-8 font-serif">Your Selected Items</h1>
        
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-10">
          {/* Cart Items */}
          <div className="lg:col-span-7 space-y-6">
            <div className="space-y-4">
              {cart.map((item) => (
                <div key={item.id} className="flex gap-5 p-5 rounded-2xl bg-white luxury-shadow border border-border hover:border-secondary/30 transition-all">
                  <div className="h-28 w-28 shrink-0 overflow-hidden rounded-xl bg-muted border border-border">
                    <img src={item.image} alt={item.name} className="h-full w-full object-cover" />
                  </div>
                  <div className="flex-1 flex flex-col justify-between">
                    <div>
                      <div className="flex justify-between items-start mb-1">
                        <Link to={`/product/${item.slug}`} className="hover:text-secondary transition-colors">
                          <h3 className="text-base font-bold font-serif text-primary">{item.name}</h3>
                        </Link>
                        <button 
                          onClick={() => removeFromCart(item.id)}
                          className="text-muted-foreground hover:text-destructive transition-colors p-1"
                          aria-label="Remove item"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                      <p className="text-xs text-muted-foreground line-clamp-1 mb-2">{item.description}</p>
                    </div>

                    <div className="flex items-center justify-between pt-2">
                      <div className="flex items-center border border-border rounded-full px-3 py-1 bg-muted/20">
                        <button 
                          onClick={() => updateQuantity(item.id, item.quantity - 1)}
                          className="p-1 hover:text-secondary"
                        >
                          <Minus className="h-3 w-3" />
                        </button>
                        <span className="w-6 text-center text-xs font-bold">{item.quantity}</span>
                        <button 
                          onClick={() => updateQuantity(item.id, item.quantity + 1)}
                          className="p-1 hover:text-secondary"
                        >
                          <Plus className="h-3 w-3" />
                        </button>
                      </div>
                      <span className="text-base font-bold text-primary">{formatINR(item.price * item.quantity)}</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>

            <div className="pt-4 flex justify-between items-center">
              <Link to="/products" className="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-primary hover:text-secondary transition-colors">
                <ArrowLeft className="h-4 w-4" />
                Continue Shopping
              </Link>
            </div>
          </div>

          {/* Order Summary Card */}
          <div className="lg:col-span-5">
            <div className="bg-primary text-white p-8 rounded-3xl sticky top-28 border border-secondary/20 shadow-2xl">
              <h3 className="text-2xl font-bold font-serif mb-6 text-white">Order Summary</h3>
              
              <div className="space-y-3 mb-6 text-sm text-white/80 border-b border-white/10 pb-6">
                <div className="flex justify-between">
                  <span>Bag Subtotal</span>
                  <span className="font-semibold text-white">{formatINR(cartTotal)}</span>
                </div>
                <div className="flex justify-between">
                  <span>Bangalore Delivery</span>
                  <span className="font-semibold text-white">Standard</span>
                </div>

              </div>

              <div className="flex justify-between items-baseline mb-8">
                <div>
                  <span className="text-xs uppercase tracking-widest text-white/60 font-semibold">Total Amount</span>
                  <p className="text-[10px] text-white/50">Includes all GST & charges</p>
                </div>
                <span className="text-3xl font-extrabold gold-gradient-text">
                  {formatINR(cartTotal)}
                </span>
              </div>

              <Link to="/checkout">
                <Button variant="secondary" className="w-full h-14 text-base font-bold rounded-full mb-6 shadow-xl gold-glow hover-lift">
                  Proceed to Checkout →
                </Button>
              </Link>

              <div className="space-y-3 pt-4 border-t border-white/10 text-xs text-white/70">
                <div className="flex items-center gap-2">
                  <Gift className="h-4 w-4 text-secondary" />
                  <span>Includes Handwritten Silk Message Card</span>
                </div>
                <div className="flex items-center gap-2">
                  <ShieldCheck className="h-4 w-4 text-secondary" />
                  <span>256-Bit SSL Encrypted Checkout</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
