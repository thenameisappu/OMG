import { Truck, Clock, MapPin, Package, ShieldCheck, Sparkles, CheckCircle2 } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

const BANGALORE_ZONES = [
  'Indiranagar', 'Koramangala', 'Whitefield', 'HSR Layout', 'Jayanagar',
  'MG Road & UB City', 'Electronic City', 'Yelahanka', 'Sarjapur Road',
  'Hebbal', 'Marathahalli', 'BTM Layout', 'JP Nagar', 'Sadashivanagar'
];

export default function DeliveryInfo() {
  return (
    <div className="min-h-screen bg-white">
      {/* Header Banner */}
      <div className="bg-primary text-white py-20 relative overflow-hidden">
        <div className="container relative z-10 text-center max-w-3xl space-y-4">
          <div className="inline-flex items-center gap-2 px-4 py-1 rounded-full bg-secondary/20 text-secondary text-xs font-bold uppercase tracking-widest border border-secondary/30">
            <Truck className="h-4 w-4" /> Bangalore Express Delivery Network
          </div>
          <h1 className="text-4xl md:text-6xl font-bold font-serif">Delivery & Shipping Info</h1>
          <p className="text-white/80 text-base font-light">
            Climate-controlled luxury transport ensuring 100% farm-fresh bloom delivery to your doorstep.
          </p>
        </div>
      </div>

      <div className="container max-w-5xl py-16">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
          <DeliverySlotCard
            icon={Truck}
            title="Same-Day Express"
            time="Order by 2:00 PM"
            desc="Available 7 days a week across Bangalore for urgent surprises and same-day celebrations."
            tag="Most Popular"
          />
          <DeliverySlotCard
            icon={Clock}
            title="Scheduled Slots"
            time="Choose Custom Hour"
            desc="Morning (9-12 PM), Afternoon (1-5 PM), or Evening (6-9 PM) guaranteed delivery windows."
            tag="Flexible"
          />
          <DeliverySlotCard
            icon={Package}
            title="Midnight Surprise"
            time="12:00 AM Sharp"
            desc="Doorstep surprise delivery at the exact stroke of midnight with live acoustic musicians option."
            tag="Exclusive"
          />
        </div>

        <div className="space-y-8">
          <Card className="rounded-3xl border-border shadow-md overflow-hidden">
            <CardContent className="p-8 md:p-10">
              <div className="flex flex-col md:flex-row items-start gap-6">
                <div className="p-4 rounded-2xl bg-secondary/10 text-secondary border border-secondary/20">
                  <ShieldCheck className="h-8 w-8" />
                </div>
                <div className="space-y-3 flex-1">
                  <h3 className="text-2xl font-bold font-serif text-primary">Temperature-Regulated Fleet</h3>
                  <p className="text-muted-foreground text-sm leading-relaxed">
                    Unlike standard courier services, OMG operates a dedicated fleet of climate-controlled delivery vans in Bangalore. Stems stay hydrated in specialized water packs to prevent wilting during transit.
                  </p>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 text-xs font-semibold text-primary">
                    <span className="flex items-center gap-1.5"><CheckCircle2 className="h-4 w-4 text-secondary" /> Sealed Eco-Velvet Protection</span>
                    <span className="flex items-center gap-1.5"><CheckCircle2 className="h-4 w-4 text-secondary" /> Real-Time Live Driver Tracking</span>
                    <span className="flex items-center gap-1.5"><CheckCircle2 className="h-4 w-4 text-secondary" /> Handwritten Wax-Sealed Card</span>
                    <span className="flex items-center gap-1.5"><CheckCircle2 className="h-4 w-4 text-secondary" /> Zero-Contact Handover Available</span>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Coverage Zones Grid */}
          <Card className="rounded-3xl border-border shadow-md overflow-hidden">
            <CardContent className="p-8 md:p-10">
              <div className="flex items-start gap-6">
                <div className="p-4 rounded-2xl bg-secondary/10 text-secondary border border-secondary/20 hidden sm:block">
                  <MapPin className="h-8 w-8" />
                </div>
                <div className="space-y-4 flex-1">
                  <div>
                    <h3 className="text-2xl font-bold font-serif text-primary mb-1">Bangalore Coverage Zones</h3>
                    <p className="text-muted-foreground text-sm">We provide same-day express delivery across all Bangalore hubs and tech parks.</p>
                  </div>
                  
                  <div className="flex flex-wrap gap-2.5 pt-2">
                    {BANGALORE_ZONES.map((zone) => (
                      <span key={zone} className="px-3.5 py-1.5 rounded-full bg-muted/40 border border-border text-xs font-semibold text-primary flex items-center gap-1">
                        <Sparkles className="h-3 w-3 text-secondary" /> {zone}
                      </span>
                    ))}
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}

function DeliverySlotCard({ icon: Icon, title, time, desc, tag }: { icon: any; title: string; time: string; desc: string; tag: string }) {
  return (
    <div className="bg-white p-8 rounded-3xl border border-border luxury-shadow flex flex-col justify-between hover:-translate-y-1 transition-all">
      <div>
        <div className="flex justify-between items-start mb-6">
          <div className="p-3.5 rounded-2xl bg-secondary/10 text-secondary border border-secondary/20">
            <Icon className="h-6 w-6" />
          </div>
          <span className="text-[10px] font-extrabold uppercase tracking-widest bg-primary text-secondary px-3 py-1 rounded-full">
            {tag}
          </span>
        </div>
        <h3 className="text-xl font-bold font-serif text-primary mb-1">{title}</h3>
        <p className="text-xs font-bold text-secondary mb-3">{time}</p>
        <p className="text-xs text-muted-foreground leading-relaxed">{desc}</p>
      </div>
    </div>
  );
}
