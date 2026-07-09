import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { User, MapPin, Phone, Mail, Save, Loader2, LogOut } from 'lucide-react';
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
            // Don't show toast on 404/empty profile, just let user fill it
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
                title: "Profile Updated",
                description: "Your detailed have been saved successfully.",
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
            <div className="min-h-screen bg-white flex items-center justify-center">
                <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-white py-12">
            <div className="container max-w-2xl">
                <div className="flex items-center justify-between mb-8">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight mb-2">My Profile</h1>
                        <p className="text-muted-foreground">Manage your personal information</p>
                    </div>
                    <Button variant="outline" className="text-destructive hover:bg-destructive/10 border-destructive/20" onClick={handleLogout}>
                        <LogOut className="h-4 w-4 mr-2" />
                        Sign Out
                    </Button>
                </div>

                <div className="grid gap-8">
                    {/* Account Info Card */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-2">
                                <User className="h-5 w-5 text-secondary" />
                                <CardTitle>Account Information</CardTitle>
                            </div>
                            <CardDescription>Details used for login</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label>Email Address</Label>
                                    <div className="flex items-center h-10 w-full rounded-md border border-input bg-muted px-3 py-2 text-sm text-muted-foreground cursor-not-allowed">
                                        <Mail className="h-4 w-4 mr-2 opacity-50" />
                                        {user?.email}
                                    </div>
                                    <p className="text-[10px] text-muted-foreground">Email cannot be changed</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Personal Details Form */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-2">
                                <MapPin className="h-5 w-5 text-secondary" />
                                <CardTitle>Delivery Information</CardTitle>
                            </div>
                            <CardDescription>Default details for your orders</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Full Name</Label>
                                    <div className="relative">
                                        <User className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            id="name"
                                            name="name"
                                            placeholder="Your User Name"
                                            value={profile.name}
                                            onChange={handleChange}
                                            className="pl-9"
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Phone Number</Label>
                                    <div className="relative">
                                        <Phone className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            id="phone"
                                            name="phone"
                                            placeholder="+91 98765 43210"
                                            value={profile.phone}
                                            onChange={handleChange}
                                            className="pl-9"
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="address">Address</Label>
                                        <Input
                                            id="address"
                                            name="address"
                                            placeholder="Flat/House No, Street"
                                            value={profile.address}
                                            onChange={handleChange}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="city">City</Label>
                                        <Input
                                            id="city"
                                            name="city"
                                            placeholder="Bangalore"
                                            value={profile.city}
                                            onChange={handleChange}
                                        />
                                    </div>
                                </div>

                                <div className="pt-4 flex justify-end">
                                    <Button type="submit" disabled={saving} className="bg-primary text-white hover:bg-primary/90 min-w-[120px]">
                                        {saving ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Saving...
                                            </>
                                        ) : (
                                            <>
                                                <Save className="mr-2 h-4 w-4" />
                                                Save Changes
                                            </>
                                        )}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}
