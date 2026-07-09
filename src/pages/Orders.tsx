import { useState, useEffect } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { Package, Clock, CheckCircle2, XCircle, Truck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useAuth } from '@/contexts/AuthContext';
import { useToast } from '@/hooks/use-toast';
import { getUserOrders, cancelOrder } from '@/db/api';
import { formatINR } from '@/lib/currency';

export default function Orders() {
    const [orders, setOrders] = useState<any[]>([]);
    const [localLoading, setLocalLoading] = useState(true);
    const { isAuthenticated, loading: authLoading } = useAuth();
    const { toast } = useToast();
    const navigate = useNavigate();
    const location = useLocation();

    useEffect(() => {
        if (!authLoading) {
            if (!isAuthenticated) {
                navigate('/login', { state: { from: location } });
                return;
            }
            fetchOrders();
        }
    }, [isAuthenticated, authLoading, location]);

    const fetchOrders = async () => {
        try {
            setLocalLoading(true);
            const data = await getUserOrders();
            setOrders(data);
        } catch (error: any) {
            console.error('Error fetching orders:', error);
            toast({
                title: "Error fetching orders",
                description: error.response?.data?.message || error.message || "Failed to load order history.",
                variant: "destructive",
            });
        } finally {
            setLocalLoading(false);
        }
    };

    const handleCancelOrder = async (orderId: string) => {
        if (!confirm('Are you sure you want to cancel this order?')) return;

        try {
            await cancelOrder(orderId);
            toast({
                title: "Order Cancelled",
                description: "Your order has been successfully cancelled.",
            });
            fetchOrders(); // Refresh the list
        } catch (error: any) {
            console.error('Error cancelling order:', error);
            toast({
                title: "Error cancelling order",
                description: error.response?.data?.message || error.message || "Failed to cancel order.",
                variant: "destructive",
            });
        }
    };

    const getStatusColor = (status: string) => {
        switch (status.toLowerCase()) {
            case 'pending': return 'bg-yellow-100 text-yellow-800';
            case 'processing': return 'bg-blue-100 text-blue-800';
            case 'shipped': return 'bg-indigo-100 text-indigo-800';
            case 'delivered': return 'bg-green-100 text-green-800';
            case 'cancelled': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const getStatusIcon = (status: string) => {
        switch (status.toLowerCase()) {
            case 'pending': return <Clock className="h-4 w-4" />;
            case 'processing': return <Package className="h-4 w-4" />;
            case 'shipped': return <Truck className="h-4 w-4" />;
            case 'delivered': return <CheckCircle2 className="h-4 w-4" />;
            case 'cancelled': return <XCircle className="h-4 w-4" />;
            default: return <Clock className="h-4 w-4" />;
        }
    };

    if (authLoading || localLoading) {
        return (
            <div className="min-h-screen bg-white py-12">
                <div className="container">
                    <div className="animate-pulse space-y-4">
                        <div className="h-10 bg-muted rounded w-1/4 mb-8"></div>
                        {[1, 2, 3].map(i => (
                            <div key={i} className="h-48 bg-muted rounded-xl"></div>
                        ))}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-white py-12">
            <div className="container max-w-5xl">
                <div className="flex items-center justify-between mb-8">
                    <div>
                        <h1 className="text-3xl md:text-4xl font-bold tracking-tight mb-2">My Orders</h1>
                        <p className="text-muted-foreground">Track and manage your recent purchases</p>
                    </div>
                    <Link to="/products">
                        <Button variant="outline">Continue Shopping</Button>
                    </Link>
                </div>

                {orders.length === 0 ? (
                    <Card className="p-16 text-center">
                        <Package className="h-16 w-16 mx-auto mb-4 text-muted-foreground" />
                        <h2 className="text-2xl font-bold mb-2">No orders yet</h2>
                        <p className="text-muted-foreground mb-6">
                            You haven't placed any orders yet.
                        </p>
                        <Link to="/products">
                            <Button size="lg" className="bg-primary text-white hover:bg-primary/90">
                                Start Shopping
                            </Button>
                        </Link>
                    </Card>
                ) : (
                    <div className="space-y-6">
                        {orders.map((order) => (
                            <Card key={order.id} className="overflow-hidden hover:shadow-md transition-shadow">
                                <CardHeader className="bg-muted/30 border-b p-6">
                                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <div className="flex gap-6 text-sm">
                                            <div>
                                                <span className="block text-muted-foreground mb-1">Order Placed</span>
                                                <span className="font-medium text-foreground">
                                                    {new Date(order.created_at).toLocaleDateString()}
                                                </span>
                                            </div>
                                            <div>
                                                <span className="block text-muted-foreground mb-1">Total Amount</span>
                                                <span className="font-medium text-foreground">{formatINR(order.total_amount)}</span>
                                            </div>
                                            <div>
                                                <span className="block text-muted-foreground mb-1">Order ID</span>
                                                <span className="font-medium text-foreground">#{order.id.slice(0, 8).toUpperCase()}</span>
                                            </div>
                                        </div>
                                        <div>
                                            <Badge className={`flex items-center gap-1 px-3 py-1 ${getStatusColor(order.status)} border-none`}>
                                                {getStatusIcon(order.status)}
                                                <span className="capitalize">{order.status}</span>
                                            </Badge>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="p-6">
                                    <div className="space-y-6">
                                        {order.order_items?.map((item: any, idx: number) => (
                                            <div key={idx} className="flex gap-4 items-center">
                                                <div className="h-20 w-20 flex-shrink-0 bg-muted rounded-md overflow-hidden">
                                                    {item.products?.image ? (
                                                        <img src={item.products.image} alt={item.products.name} className="h-full w-full object-cover" />
                                                    ) : (
                                                        <div className="h-full w-full flex items-center justify-center text-muted-foreground text-xs">No Image</div>
                                                    )}
                                                </div>
                                                <div className="flex-1">
                                                    <h4 className="font-bold text-foreground mb-1">{item.products?.name || "Product Name"}</h4>
                                                    <p className="text-sm text-muted-foreground">Qty: {item.quantity}</p>
                                                </div>
                                                <div className="font-bold text-foreground">
                                                    {formatINR(item.unit_price)}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                                <CardFooter className="bg-muted/10 p-4 flex justify-between items-center text-sm border-t">
                                    <div className="flex gap-2">
                                        {!(order.status.toLowerCase() === 'delivered' || order.status.toLowerCase() === 'cancelled') && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="text-red-500 hover:text-red-600 hover:bg-red-50 border-red-200"
                                                disabled={!['pending', 'order accepted'].includes(order.status.toLowerCase())}
                                                onClick={() => handleCancelOrder(order.id)}
                                            >
                                                Cancel Order
                                            </Button>
                                        )}
                                    </div>
                                    <div className="text-muted-foreground">
                                        Delivering to: <span className="font-medium text-foreground">{order.delivery_address || 'Address not available'}</span>
                                    </div>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
