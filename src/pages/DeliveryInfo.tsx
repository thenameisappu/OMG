import { Truck, Clock, MapPin, Package } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

export default function DeliveryInfo() {
  return (
    <div className="min-h-screen bg-white py-16">
      <div className="container max-w-4xl">
        <h1 className="text-4xl md:text-5xl font-bold mb-4">Delivery Information</h1>
        <p className="text-lg text-muted-foreground mb-12">
          Everything you need to know about our delivery services
        </p>

        <div className="space-y-8">
          <Card>
            <CardContent className="p-8">
              <div className="flex items-start gap-4">
                <Truck className="h-8 w-8 text-secondary flex-shrink-0 mt-1" />
                <div>
                  <h2 className="text-2xl font-bold mb-3">Same-Day Delivery</h2>
                  <p className="text-muted-foreground mb-4">
                    Order before 1 PM for same-day delivery in Bangalore, Karnataka. Perfect for last-minute surprises and urgent occasions.
                  </p>
                  <ul className="list-disc list-inside space-y-2 text-muted-foreground">
                    <li>Available Monday to Sunday</li>
                    <li>Order by 1 PM for same-day delivery</li>
                    <li>Premium priority handling</li>
                  </ul>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-8">
              <div className="flex items-start gap-4">
                <Clock className="h-8 w-8 text-secondary flex-shrink-0 mt-1" />
                <div>
                  <h2 className="text-2xl font-bold mb-3">Scheduled Delivery</h2>
                  <p className="text-muted-foreground mb-4">
                    Choose your preferred date and time slot for delivery. Perfect for planning ahead and ensuring your gift arrives exactly when you want it.
                  </p>
                  <ul className="list-disc list-inside space-y-2 text-muted-foreground">
                    <li>Morning (09:00 - 12:00)</li>
                    <li>Afternoon (13:00 - 17:00)</li>
                    <li>Evening (18:00 - 21:00)</li>
                  </ul>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-8">
              <div className="flex items-start gap-4">
                <Package className="h-8 w-8 text-secondary flex-shrink-0 mt-1" />
                <div>
                  <h2 className="text-2xl font-bold mb-3">Midnight Surprise</h2>
                  <p className="text-muted-foreground mb-4">
                    Make birthdays and anniversaries extra special with our midnight delivery service. Your gift will arrive at exactly 12:00 AM.
                  </p>
                  <ul className="list-disc list-inside space-y-2 text-muted-foreground">
                    <li>Delivery at 12:00 AM sharp</li>
                    <li>Perfect for birthdays and anniversaries</li>
                    <li>Advance booking required</li>
                  </ul>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-8">
              <div className="flex items-start gap-4">
                <MapPin className="h-8 w-8 text-secondary flex-shrink-0 mt-1" />
                <div>
                  <h2 className="text-2xl font-bold mb-3">Service Area</h2>
                  <p className="text-muted-foreground mb-4">
                    We currently deliver to all areas within Bangalore, Karnataka. Our delivery partners ensure your flowers arrive fresh and beautiful.
                  </p>
                  <ul className="list-disc list-inside space-y-2 text-muted-foreground">
                    <li>All areas in Bangalore covered</li>
                    <li>Temperature-controlled delivery vehicles</li>
                    <li>Professional handling by trained staff</li>
                  </ul>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
