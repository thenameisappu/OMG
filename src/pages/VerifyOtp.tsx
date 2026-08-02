import { useState, useEffect } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useAuth } from '@/contexts/AuthContext';
import { Loader2, ArrowLeft } from 'lucide-react';

export default function VerifyOtp() {
    const navigate = useNavigate();
    const location = useLocation();
    const { verifyOtp, resendOtp } = useAuth();
    const [loading, setLoading] = useState(false);
    const [resending, setResending] = useState(false);
    const [otp, setOtp] = useState('');

    const [cooldown, setCooldown] = useState(60);

    const email = location.state?.email;

    useEffect(() => {
        if (!email) {
            navigate('/login');
        }
    }, [email, navigate]);

    useEffect(() => {
        let timer: NodeJS.Timeout;
        if (cooldown > 0) {
            timer = setInterval(() => {
                setCooldown((prev) => prev - 1);
            }, 1000);
        }
        return () => {
            if (timer) clearInterval(timer);
        };
    }, [cooldown]);

    const handleVerify = async (e: React.FormEvent) => {
        e.preventDefault();
        if (otp.length !== 6) return;

        setLoading(true);
        try {
            await verifyOtp(email, otp);
            navigate('/profile', { replace: true });
        } catch (error) {
            console.error('Verification error:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleResend = async () => {
        if (cooldown > 0) return;
        setResending(true);
        try {
            await resendOtp(email);
            setCooldown(60);
        } catch (error) {
            console.error('Resend error:', error);
        } finally {
            setResending(false);
        }
    };

    if (!email) return null;

    return (
        <div className="min-h-screen bg-white flex items-center justify-center py-16 px-4">
            <Card className="w-full max-w-md">
                <CardHeader className="text-center">
                    <CardTitle className="text-3xl font-bold text-primary">Verify Your Email</CardTitle>
                    <CardDescription>
                        We've sent a 6-digit verification code to <span className="font-semibold text-foreground">{email}</span>.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handleVerify} className="space-y-6">
                        <div className="space-y-2">
                            <Label htmlFor="otp">One-Time Password</Label>
                            <Input
                                id="otp"
                                type="text"
                                placeholder="000000"
                                value={otp}
                                onChange={(e) => setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))}
                                required
                                className="text-center text-2xl tracking-[0.5em] font-mono h-14"
                                disabled={loading}
                            />
                            <p className="text-xs text-muted-foreground text-center">
                                Enter the 6-digit code sent to your email.
                            </p>
                        </div>

                        <Button type="submit" className="w-full" disabled={loading || otp.length !== 6}>
                            {loading ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Verifying...
                                </>
                            ) : (
                                'Verify Email'
                            )}
                        </Button>
                    </form>

                    <div className="mt-6 flex flex-col items-center space-y-4">
                        <button
                            onClick={handleResend}
                            disabled={resending || loading || cooldown > 0}
                            className="text-sm font-medium text-primary hover:underline disabled:opacity-50"
                        >
                            {resending ? 'Sending...' : cooldown > 0 ? `Resend OTP code in ${cooldown}s` : "Didn't receive a code? Resend OTP"}
                        </button>
                        <p className="text-[11px] text-muted-foreground text-center">
                            ⏱️ OTP is valid for 10 minutes.
                        </p>

                        <Link
                            to="/login"
                            className="inline-flex items-center text-sm text-muted-foreground hover:text-primary transition-colors"
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to login
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
