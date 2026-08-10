import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { User, MapPin, Phone, Mail, Save, Loader2, LogOut, Package, Heart, Crown, Sparkles, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuth } from '@/contexts/AuthContext';
import { useToast } from '@/hooks/use-toast';
import { getUserProfile, updateUserProfile } from '@/db/api';

export default function Profile() {
    const { user, signOut, isAuthenticated, loading: authLoading } = useAuth();
    const navigate = useNavigate();
    const { toast } = useToast();

    const [localLoading, setLocalLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [profile, setProfile] = useState({
        name: '',
        phone: '',
        address: '',
        city: ''
    });

    useEffect(() => {
        if (!authLoading) {
            if (!isAuthenticated) {
                navigate('/login');
                return;
            }
            fetchProfile();
        }
    }, [isAuthenticated, authLoading, navigate]);

    const fetchProfile = async () => {
        try {
            setLocalLoading(true);
            const data = await getUserProfile();
            if (data) {
                setProfile({
                    name: data.name || '',
                    phone: data.phone || '',
                    address: data.address || '',
                    city: data.city || ''
                });
            }
        } catch (error) {
            console.error('Error fetching profile:', error);
        } finally {
            setLocalLoading(false);
        }
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const { name, value } = e.target;
        setProfile(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            await updateUserProfile(profile);
            toast({
                title: "Profile Saved",
                description: "Your delivery information has been updated.",
            });
        } catch (error) {
            console.error('Error updating profile:', error);
            toast({
                title: "Update Failed",
                description: "Could not save your profile. Please try again.",
                variant: "destructive",
            });
        } finally {
            setSaving(false);
        }
    };

    const handleLogout = async () => {
        await signOut();
        navigate('/');
    };

    if (authLoading || localLoading) {
        return (
            <div className="min-h-screen bg-white flex flex-col items-center justify-center py-32">
                <Loader2 className="h-8 w-8 animate-spin text-secondary mb-3" />
                <p className="text-xs uppercase tracking-widest font-bold text-primary">Loading Concierge Profile...</p>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-white py-12">
            <div className="container max-w-4xl">
                {/* VIP Header Banner */}
                <div className="bg-primary text-white p-8 md:p-10 rounded-3xl mb-8 relative overflow-hidden shadow-xl border border-secondary/30">
                    <div className="absolute top-0 right-0 w-64 h-64 bg-secondary/10 rounded-full blur-3xl pointer-events-none" />
                    <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-6 relative z-10">
                        <div className="flex items-center gap-5">
                            <div className="w-16 h-16 rounded-full bg-gradient-to-tr from-amber-400 to-amber-600 text-black flex items-center justify-center font-bold text-2xl font-serif gold-glow shadow-md">
                                {user?.email?.charAt(0).toUpperCase() || 'U'}
                            </div>
                            <div>
                                <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-secondary/20 text-secondary text-[10px] font-extrabold uppercase tracking-widest border border-secondary/30 mb-1">
                                    <User className="h-3 w-3 text-secondary" /> Customer Account
                                </div>
                                <h1 className="text-2xl md:text-3xl font-bold font-serif text-white">{profile.name || user?.email?.split('@')[0]}</h1>
                                <p className="text-xs text-white/70">{user?.email}</p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <Link to="/orders">
                                <Button
                                    size="lg"
                                    className="h-8 px-5 rounded-full bg-gradient-to-r from-amber-400 via-amber-500 to-amber-600 text-black font-bold text-lg hover:brightness-110 transition-all shadow-lg hover:scale-105 gold-glow"
                                    onClick={() => navigate('/orders')}
                                >
                                    <Package className="h-3.5 w-3.5 text-secondary" />
                                    My Orders
                                </Button>
                            </Link>
                            <Button
                                size="lg"
                                className="h-8 px-5 rounded-full bg-gradient-to-r from-amber-400 via-amber-500 to-amber-600 text-black font-bold text-lg hover:brightness-110 transition-all shadow-lg hover:scale-105 gold-glow"
                                onClick={handleLogout}
                            >
                                <LogOut className="h-3.5 w-3.5" />
                                Sign Out
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                    {/* Sidebar navigation card */}
                    <div className="space-y-4">
                        <div className="p-5 rounded-2xl bg-muted/20 border border-border space-y-2">
                            <span className="text-[10px] font-bold text-muted-foreground uppercase tracking-widest block mb-2">Account Portal</span>
                            <Link to="/profile" className="flex items-center gap-3 p-3 rounded-xl bg-white border border-secondary/40 font-bold text-sm text-primary shadow-sm">
                                <User className="h-4 w-4 text-secondary" /> Personal Profile
                            </Link>
                            <Link to="/orders" className="flex items-center gap-3 p-3 rounded-xl hover:bg-white/60 font-semibold text-sm text-muted-foreground transition-colors">
                                <Package className="h-4 w-4 text-secondary" /> My Order History
                            </Link>
                            <Link to="/wishlist" className="flex items-center gap-3 p-3 rounded-xl hover:bg-white/60 font-semibold text-sm text-muted-foreground transition-colors">
                                <Heart className="h-4 w-4 text-secondary" /> Wishlist Items
                            </Link>
                        </div>
                    </div>

                    {/* Main profile form */}
                    <div className="md:col-span-2 space-y-6">
                        <Card className="rounded-3xl border-border shadow-md overflow-hidden">
                            <CardHeader className="bg-muted/10 border-b border-border p-6">
                                <CardTitle className="font-serif text-xl">Delivery Address & Details</CardTitle>
                                <CardDescription className="text-xs">Update your default recipient information for 1-click checkout.</CardDescription>
                            </CardHeader>
                            <CardContent className="p-6">
                                <form onSubmit={handleSubmit} className="space-y-4">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="name" className="text-xs font-bold uppercase tracking-wider">Full Name</Label>
                                        <div className="relative">
                                            <User className="absolute left-3 top-3.5 h-4 w-4 text-muted-foreground" />
                                            <Input
                                                id="name"
                                                name="name"
                                                placeholder="Enter full name"
                                                className="pl-10 h-11 text-sm rounded-xl"
                                                value={profile.name}
                                                onChange={handleChange}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="phone" className="text-xs font-bold uppercase tracking-wider">Phone / WhatsApp</Label>
                                        <div className="relative">
                                            <Phone className="absolute left-3 top-3.5 h-4 w-4 text-muted-foreground" />
                                            <Input
                                                id="phone"
                                                name="phone"
                                                type="tel"
                                                placeholder="+91 98765 43210"
                                                className="pl-10 h-11 text-sm rounded-xl"
                                                value={profile.phone}
                                                onChange={handleChange}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="address" className="text-xs font-bold uppercase tracking-wider">Street / Area Address</Label>
                                        <div className="relative">
                                            <MapPin className="absolute left-3 top-3.5 h-4 w-4 text-muted-foreground" />
                                            <Input
                                                id="address"
                                                name="address"
                                                placeholder="Flat, House No., Street, Area"
                                                className="pl-10 h-11 text-sm rounded-xl"
                                                value={profile.address}
                                                onChange={handleChange}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label htmlFor="city" className="text-xs font-bold uppercase tracking-wider">City</Label>
                                        <Input
                                            id="city"
                                            name="city"
                                            placeholder="e.g. Bangalore"
                                            className="h-11 text-sm rounded-xl"
                                            value={profile.city}
                                            onChange={handleChange}
                                        />
                                    </div>

                                    <Button type="submit" variant="secondary" className="w-full h-12 font-bold rounded-full mt-4 shadow-md gold-glow" disabled={saving}>
                                        {saving ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Saving Profile...
                                            </>
                                        ) : (
                                            <>
                                                <Save className="mr-2 h-4 w-4" />
                                                Save Information
                                            </>
                                        )}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    );
}
