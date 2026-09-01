import { useState, useEffect } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAuth } from '@/contexts/AuthContext';
import { Loader2, Sparkles, Lock, Mail, User, ShieldCheck, KeyRound, ArrowLeft, CheckCircle2 } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';

export default function Login() {
  const navigate = useNavigate();
  const location = useLocation();
  const { toast } = useToast();
  const { signIn, signUp, verifyOtp, resendOtp, isAuthenticated } = useAuth();
  const [loading, setLoading] = useState(false);

  const from = (location.state as any)?.from?.pathname || '/';

  useEffect(() => {
    if (isAuthenticated) {
      navigate(from, { replace: true });
    }
  }, [isAuthenticated, navigate, from]);

  const [loginData, setLoginData] = useState({
    email: '',
    password: '',
  });

  const [signupData, setSignupData] = useState({
    name: '',
    email: '',
    password: '',
    confirmPassword: '',
  });

  // OTP Verification In-Place Step State
  const [otpStep, setOtpStep] = useState(false);
  const [otpCode, setOtpCode] = useState('');
  const [cooldown, setCooldown] = useState(0);
  const [resending, setResending] = useState(false);

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

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await signIn(loginData.email, loginData.password);
      navigate(from, { replace: true });
    } catch (error: any) {
      console.error('Login error:', error);
      if (error.response?.status === 403 && error.response?.data?.requires_verification) {
        setSignupData((prev) => ({ ...prev, email: loginData.email }));
        if (error.response.data.dev_otp) setOtpCode(error.response.data.dev_otp);
        setOtpStep(true);
        setCooldown(60);
      }
    } finally {
      setLoading(false);
    }
  };

  const handleSignup = async (e: React.FormEvent) => {
    e.preventDefault();

    if (signupData.password !== signupData.confirmPassword) {
      toast({
        title: 'Password Mismatch',
        description: 'Passwords do not match. Please check and try again.',
        variant: 'destructive',
      });
      return;
    }

    setLoading(true);
    try {
      const response = await signUp(signupData.email, signupData.password, signupData.name);
      if (response?.dev_otp) setOtpCode(response.dev_otp);
      setOtpStep(true);
      setCooldown(60);
      toast({
        title: 'Verification Code Sent ✨',
        description: `A 6-digit OTP code has been sent to ${signupData.email}.`,
      });
    } catch (error: any) {
      console.error('Signup error:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleVerifyOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    if (otpCode.length !== 6) {
      toast({
        title: 'Invalid Code',
        description: 'Please enter all 6 numeric digits of your OTP.',
        variant: 'destructive',
      });
      return;
    }

    setLoading(true);
    try {
      await verifyOtp(signupData.email, otpCode);
      toast({
        title: 'Account Activated! 🎉',
        description: 'Your email has been verified successfully. Welcome to OH MY GUDNESS.',
      });
      navigate(from, { replace: true });
    } catch (error: any) {
      console.error('OTP verification error:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleResendOtp = async () => {
    if (cooldown > 0) return;
    setResending(true);
    try {
      const response = await resendOtp(signupData.email);
      if (response?.dev_otp) setOtpCode(response.dev_otp);
      setCooldown(60);
      toast({
        title: 'New Code Sent ✨',
        description: 'A fresh 6-digit OTP code has been sent to your email.',
      });
    } catch (error: any) {
      console.error('Resend error:', error);
    } finally {
      setResending(false);
    }
  };

  return (
    <div className="min-h-screen bg-white flex items-center justify-center">
      <div className="w-full min-h-screen grid grid-cols-1 lg:grid-cols-12">
        {/* Left Side: Luxury Artwork Showcase */}
        <div className="hidden lg:flex lg:col-span-6 relative bg-primary items-center justify-center p-12 overflow-hidden">
          <div className="absolute inset-0">
            <img
              src="https://miaoda-site-img.s3cdn.medo.dev/images/KLing_03fde315-7935-4968-98e6-d7b417bd3057.jpg"
              alt="Luxury Floral Signature"
              className="w-full h-full object-cover opacity-40 filter brightness-75 scale-105"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-[#070D0A] via-[#070D0A]/60 to-transparent" />
          </div>

          <div className="relative z-10 text-white max-w-lg space-y-6">
            <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-secondary/20 text-secondary text-xs font-extrabold uppercase tracking-widest border border-secondary/30">
              <Sparkles className="h-4 w-4" /> OMG Atelier Portal
            </div>

            <h2 className="text-4xl lg:text-5xl font-bold font-serif text-white leading-tight">
              Welcome to the World of <span className="gold-gradient-text italic font-serif">Luxury Floral Magic</span>
            </h2>

            <p className="text-emerald-100/80 text-sm leading-relaxed font-light">
              Sign in to manage your orders, track surprise deliveries, save favorite blooms to your wishlist, and enjoy concierge access.
            </p>

            <div className="pt-6 border-t border-white/15 flex items-center gap-6 text-xs text-white/70">
              <span className="flex items-center gap-1.5">
                <ShieldCheck className="h-4 w-4 text-secondary" /> 256-Bit Encrypted Security
              </span>
              <span className="flex items-center gap-1.5">
                <Sparkles className="h-4 w-4 text-secondary" /> Concierge Access
              </span>
            </div>
          </div>
        </div>

        {/* Right Side: Auth Form */}
        <div className="lg:col-span-6 flex items-center justify-center p-6 md:p-16 bg-white">
          <div className="w-full max-w-md space-y-8">
            <div className="text-center space-y-2">
              <Link to="/" className="inline-block mb-4">
                <img src="/images/logo/logo-navbar.png" alt="OMG Logo" className="h-12 mx-auto" />
              </Link>
              <p className="text-xs text-muted-foreground">Sign in to your account or create a new OMG profile</p>
            </div>

            <Tabs defaultValue="login" className="w-full">
              <TabsList className="grid w-full grid-cols-2 mb-6 h-12 bg-muted/30 p-1 rounded-full border border-border">
                <TabsTrigger value="login" className="rounded-full text-xs font-bold uppercase tracking-wider data-[state=active]:bg-secondary data-[state=active]:text-primary">Sign In</TabsTrigger>
                <TabsTrigger value="signup" className="rounded-full text-xs font-bold uppercase tracking-wider data-[state=active]:bg-secondary data-[state=active]:text-primary">Sign Up</TabsTrigger>
              </TabsList>

              <TabsContent value="login">
                <form onSubmit={handleLogin} className="space-y-5">
                  <div className="space-y-1.5">
                    <Label htmlFor="login-email" className="text-xs font-bold uppercase tracking-wider text-primary">Email Address</Label>
                    <div className="relative">
                      <Mail className="absolute left-3.5 top-3.5 h-4 w-4 text-muted-foreground" />
                      <Input
                        id="login-email"
                        type="email"
                        placeholder="yourname@example.com"
                        className="h-12 pl-10 text-sm rounded-xl border-border focus:border-secondary"
                        value={loginData.email}
                        onChange={(e) => setLoginData({ ...loginData, email: e.target.value })}
                        required
                        disabled={loading}
                      />
                    </div>
                  </div>

                  <div className="space-y-1.5">
                    <div className="flex items-center justify-between">
                      <Label htmlFor="login-password" className="text-xs font-bold uppercase tracking-wider text-primary">Password</Label>
                      <Link
                        to="/forgot-password"
                        className="text-xs font-bold text-secondary hover:underline"
                      >
                        Forgot Password?
                      </Link>
                    </div>
                    <div className="relative">
                      <Lock className="absolute left-3.5 top-3.5 h-4 w-4 text-muted-foreground" />
                      <Input
                        id="login-password"
                        type="password"
                        placeholder="••••••••"
                        className="h-12 pl-10 text-sm rounded-xl border-border focus:border-secondary"
                        value={loginData.password}
                        onChange={(e) => setLoginData({ ...loginData, password: e.target.value })}
                        required
                        disabled={loading}
                      />
                    </div>
                  </div>

                  <Button type="submit" variant="secondary" className="w-full h-14 rounded-full font-bold text-base shadow-lg gold-glow mt-2 hover-lift" disabled={loading}>
                    {loading ? (
                      <>
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        Signing in...
                      </>
                    ) : (
                      'Sign In to Account'
                    )}
                  </Button>
                </form>
              </TabsContent>

              <TabsContent value="signup">
                {otpStep ? (
                  <form onSubmit={handleVerifyOtp} className="space-y-5 animate-in fade-in duration-300">
                    <div className="bg-amber-500/10 border border-amber-500/30 p-4 rounded-2xl space-y-1 text-center">
                      <div className="flex items-center justify-center gap-1.5 text-amber-600 font-bold text-xs uppercase tracking-wider">
                        <KeyRound className="h-4 w-4" /> Step 2: Verify Email OTP
                      </div>
                      <p className="text-xs text-muted-foreground leading-relaxed">
                        We sent a 6-digit code to <strong className="text-foreground">{signupData.email}</strong>
                      </p>
                    </div>

                    <div className="space-y-1.5">
                      <Label htmlFor="otp-input" className="text-xs font-bold uppercase tracking-wider text-primary text-center block">
                        Enter 6-Digit One-Time Password
                      </Label>
                      <Input
                        id="otp-input"
                        type="text"
                        maxLength={6}
                        placeholder="123456"
                        value={otpCode}
                        onChange={(e) => setOtpCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                        required
                        autoFocus
                        className="h-14 text-center text-2xl font-mono tracking-[0.5em] rounded-xl border-border focus:border-secondary shadow-inner"
                        disabled={loading}
                      />
                      <p className="text-[11px] text-muted-foreground text-center pt-1">
                        ⏱️ Code valid for 10 minutes. Check your inbox/spam folder.
                      </p>
                    </div>

                    <Button type="submit" variant="secondary" className="w-full h-14 rounded-full font-bold text-base shadow-lg gold-glow hover-lift" disabled={loading || otpCode.length !== 6}>
                      {loading ? (
                        <>
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                          Activating Account...
                        </>
                      ) : (
                        'Verify OTP & Activate Account'
                      )}
                    </Button>

                    <div className="flex items-center justify-between text-xs pt-2">
                      <button
                        type="button"
                        onClick={() => setOtpStep(false)}
                        className="inline-flex items-center text-muted-foreground hover:text-foreground font-medium"
                      >
                        <ArrowLeft className="mr-1 h-3.5 w-3.5" /> Back / Edit Email
                      </button>

                      <button
                        type="button"
                        onClick={handleResendOtp}
                        disabled={resending || loading || cooldown > 0}
                        className="text-secondary font-bold hover:underline disabled:opacity-50"
                      >
                        {resending ? 'Sending...' : cooldown > 0 ? `Resend in ${cooldown}s` : 'Resend OTP'}
                      </button>
                    </div>
                  </form>
                ) : (
                  <form onSubmit={handleSignup} className="space-y-4">
                    <div className="space-y-1">
                      <Label htmlFor="signup-name" className="text-xs font-bold uppercase tracking-wider text-primary">Full Name</Label>
                      <div className="relative">
                        <User className="absolute left-3.5 top-3.5 h-4 w-4 text-muted-foreground" />
                        <Input
                          id="signup-name"
                          type="text"
                          placeholder="Enter full name"
                          className="h-11 pl-10 text-sm rounded-xl border-border"
                          value={signupData.name}
                          onChange={(e) => setSignupData({ ...signupData, name: e.target.value })}
                          required
                          disabled={loading}
                        />
                      </div>
                    </div>

                    <div className="space-y-1">
                      <Label htmlFor="signup-email" className="text-xs font-bold uppercase tracking-wider text-primary">Email Address</Label>
                      <div className="relative">
                        <Mail className="absolute left-3.5 top-3.5 h-4 w-4 text-muted-foreground" />
                        <Input
                          id="signup-email"
                          type="email"
                          placeholder="Enter email address"
                          className="h-11 pl-10 text-sm rounded-xl border-border"
                          value={signupData.email}
                          onChange={(e) => setSignupData({ ...signupData, email: e.target.value })}
                          required
                          disabled={loading}
                        />
                      </div>
                    </div>

                    <div className="space-y-1">
                      <Label htmlFor="signup-password" className="text-xs font-bold uppercase tracking-wider text-primary">Create Password</Label>
                      <div className="relative">
                        <Lock className="absolute left-3.5 top-3.5 h-4 w-4 text-muted-foreground" />
                        <Input
                          id="signup-password"
                          type="password"
                          placeholder="••••••••"
                          className="h-11 pl-10 text-sm rounded-xl border-border"
                          value={signupData.password}
                          onChange={(e) => setSignupData({ ...signupData, password: e.target.value })}
                          required
                          disabled={loading}
                        />
                      </div>
                    </div>

                    <div className="space-y-1">
                      <Label htmlFor="signup-confirm-password" className="text-xs font-bold uppercase tracking-wider text-primary">Confirm Password</Label>
                      <div className="relative">
                        <Lock className="absolute left-3.5 top-3.5 h-4 w-4 text-muted-foreground" />
                        <Input
                          id="signup-confirm-password"
                          type="password"
                          placeholder="••••••••"
                          className="h-11 pl-10 text-sm rounded-xl border-border"
                          value={signupData.confirmPassword}
                          onChange={(e) => setSignupData({ ...signupData, confirmPassword: e.target.value })}
                          required
                          disabled={loading}
                        />
                      </div>
                    </div>

                    <Button type="submit" variant="secondary" className="w-full h-14 rounded-full font-bold text-base shadow-lg gold-glow mt-2 hover-lift" disabled={loading}>
                      {loading ? (
                        <>
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                          Sending Verification OTP...
                        </>
                      ) : (
                        'Register'
                      )}
                    </Button>
                  </form>
                )}
              </TabsContent>
            </Tabs>
          </div>
        </div>
      </div>
    </div>
  );
}
