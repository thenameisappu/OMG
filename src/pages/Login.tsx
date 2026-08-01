import { useState } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAuth } from '@/contexts/AuthContext';
import { Loader2, Sparkles, Lock, Mail, User, ShieldCheck } from 'lucide-react';

export default function Login() {
  const navigate = useNavigate();
  const location = useLocation();
  const { signIn, signUp } = useAuth();
  const [loading, setLoading] = useState(false);

  const from = (location.state as any)?.from?.pathname || '/';

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

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await signIn(loginData.email, loginData.password);
      navigate(from, { replace: true });
    } catch (error: any) {
      console.error('Login error:', error);
      if (error.response?.status === 403 && error.response?.data?.requires_verification) {
        navigate('/verify-otp', { state: { email: loginData.email } });
      }
    } finally {
      setLoading(false);
    }
  };

  const handleSignup = async (e: React.FormEvent) => {
    e.preventDefault();

    if (signupData.password !== signupData.confirmPassword) {
      alert('Passwords do not match');
      return;
    }

    setLoading(true);
    try {
      await signUp(signupData.email, signupData.password, signupData.name);
      navigate(from, { replace: true });
    } catch (error) {
      console.error('Signup error:', error);
    } finally {
      setLoading(false);
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
              Sign in to manage your orders, track surprise deliveries, save favorite blooms to your wishlist, and enjoy VIP member privileges.
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
                        Creating Account...
                      </>
                    ) : (
                      'Register'
                    )}
                  </Button>
                </form>
              </TabsContent>
            </Tabs>
          </div>
        </div>
      </div>
    </div>
  );
}
