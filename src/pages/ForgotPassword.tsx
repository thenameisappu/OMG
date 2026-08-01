import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/hooks/use-toast';
import { authService } from '@/services/api';
import { Loader2, ArrowLeft, KeyRound, Mail, ShieldAlert } from 'lucide-react';

type ResetStep = 'email' | 'otp' | 'password';

export default function ForgotPassword() {
  const navigate = useNavigate();
  const { toast } = useToast();
  const [step, setStep] = useState<ResetStep>('email');
  const [loading, setLoading] = useState(false);
  const [resending, setResending] = useState(false);
  const [email, setEmail] = useState('');
  const [otp, setOtp] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  
  // Cooldown timer for OTP resend (in seconds)
  const [cooldown, setCooldown] = useState(0);

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

  const handleRequestOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email) return;

    setLoading(true);
    try {
      await authService.forgotPassword(email);
      toast({
        title: 'Reset code sent!',
        description: 'A 6-digit OTP code has been sent to your email address.',
      });
      setStep('otp');
      setCooldown(60); // Start 60-second cooldown
    } catch (error: any) {
      console.error('Request OTP error:', error);
      toast({
        title: 'Request failed',
        description: error.response?.data?.message || 'Failed to send OTP code. Please try again.',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    if (otp.length !== 6) return;

    setLoading(true);
    try {
      await authService.verifyResetOtp(email, otp);
      toast({
        title: 'OTP Verified!',
        description: 'Please set your new password.',
      });
      setStep('password');
    } catch (error: any) {
      console.error('Verify OTP error:', error);
      toast({
        title: 'Verification failed',
        description: error.response?.data?.message || 'Invalid or expired OTP code.',
        variant: 'destructive',
      });
      // If we got locked out from too many attempts, go back to step 1
      if (error.response?.status === 429) {
        setStep('email');
        setOtp('');
      }
    } finally {
      setLoading(false);
    }
  };

  const handleResetPassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (password.length < 6) {
      toast({
        title: 'Password too short',
        description: 'Password must be at least 6 characters long.',
        variant: 'destructive',
      });
      return;
    }

    if (password !== confirmPassword) {
      toast({
        title: 'Passwords mismatch',
        description: 'New password and confirm password do not match.',
        variant: 'destructive',
      });
      return;
    }

    setLoading(true);
    try {
      await authService.resetPassword(email, otp, password);
      toast({
        title: 'Password updated!',
        description: 'Your password has been successfully reset. You can now login.',
      });
      navigate('/login');
    } catch (error: any) {
      console.error('Reset password error:', error);
      toast({
        title: 'Reset failed',
        description: error.response?.data?.message || 'Failed to reset password. Please try again.',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
    }
  };

  const handleResendOtp = async () => {
    if (cooldown > 0 || resending) return;

    setResending(true);
    try {
      await authService.forgotPassword(email);
      toast({
        title: 'OTP Resent',
        description: 'A new 6-digit verification code has been sent to your email.',
      });
      setCooldown(60); // Restart 60-second cooldown
    } catch (error: any) {
      console.error('Resend OTP error:', error);
      toast({
        title: 'Resend failed',
        description: error.response?.data?.message || 'Something went wrong',
        variant: 'destructive',
      });
    } finally {
      setResending(false);
    }
  };

  return (
    <div className="min-h-screen bg-white flex items-center justify-center py-16 px-4">
      <Card className="w-full max-w-md animate-fade-in">
        <CardHeader className="text-center">
          <CardTitle className="text-3xl font-bold text-primary flex items-center justify-center gap-2">
            {step === 'email' && <Mail className="h-8 w-8 text-primary" />}
            {step === 'otp' && <ShieldAlert className="h-8 w-8 text-primary" />}
            {step === 'password' && <KeyRound className="h-8 w-8 text-primary" />}
            Reset Password
          </CardTitle>
          <CardDescription>
            {step === 'email' && "Enter your email address and we'll send you an OTP to reset your password."}
            {step === 'otp' && `We've sent a 6-digit OTP code to ${email}.`}
            {step === 'password' && "Set a strong, new password for your OMG account."}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {step === 'email' && (
            <form onSubmit={handleRequestOtp} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="reset-email">Email</Label>
                <Input
                  id="reset-email"
                  type="email"
                  placeholder="Enter your email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  disabled={loading}
                />
              </div>
              <Button type="submit" className="w-full" disabled={loading}>
                {loading ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Sending OTP...
                  </>
                ) : (
                  'Send Reset Code'
                )}
              </Button>
            </form>
          )}

          {step === 'otp' && (
            <form onSubmit={handleVerifyOtp} className="space-y-6">
              <div className="space-y-2">
                <Label htmlFor="reset-otp">One-Time Password</Label>
                <Input
                  id="reset-otp"
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
                  'Verify Code'
                )}
              </Button>
            </form>
          )}

          {step === 'password' && (
            <form onSubmit={handleResetPassword} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="new-password">New Password</Label>
                <Input
                  id="new-password"
                  type="password"
                  placeholder="At least 6 characters"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  disabled={loading}
                  minLength={6}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="confirm-new-password">Confirm Password</Label>
                <Input
                  id="confirm-new-password"
                  type="password"
                  placeholder="Confirm new password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  required
                  disabled={loading}
                  minLength={6}
                />
              </div>
              <Button type="submit" className="w-full" disabled={loading}>
                {loading ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Resetting...
                  </>
                ) : (
                  'Reset Password'
                )}
              </Button>
            </form>
          )}

          <div className="mt-6 flex flex-col items-center space-y-4">
            {step === 'otp' && (
              <button
                type="button"
                onClick={handleResendOtp}
                disabled={cooldown > 0 || resending || loading}
                className="text-sm font-medium text-primary hover:underline disabled:opacity-50"
              >
                {resending ? 'Sending...' : cooldown > 0 ? `Resend OTP in ${cooldown}s` : "Didn't receive a code? Resend"}
              </button>
            )}

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
