import { Link } from 'react-router-dom';
import { Heart, ShoppingBag, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useCart } from '@/contexts/CartContext';
import { useWishlist } from '@/contexts/WishlistContext';
import { useToast } from '@/hooks/use-toast';
import { formatINR } from '@/lib/currency';

export default function Wishlist() {
  const { wishlistItems, toggleWishlist, loading } = useWishlist();
  const { addToCart } = useCart();
  const { toast } = useToast();

  const handleRemove = (product: any) => {
    toggleWishlist(product);
  };

  const handleAddToCart = (productDetails: any) => {
    const p = productDetails.products ? {
      id: productDetails.product_id || productDetails.id,
      name: productDetails.products.name,
      price: productDetails.products.price,
      image: productDetails.products.image_url,
      slug: productDetails.products.slug
    } : productDetails;

    addToCart(p as any, 1);
    toast({
      title: "Added to Shopping Bag",
      description: `${p.name} has been added to your shopping bag.`,
    });
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-white py-12">
        <div className="container">
          <div className="animate-pulse">
            <div className="h-12 bg-muted rounded w-1/3 mb-4"></div>
            <div className="h-6 bg-muted rounded w-1/2 mb-8"></div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              {[1, 2, 3].map(i => (
                <div key={i} className="h-96 bg-muted rounded"></div>
              ))}
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white py-12">
      <div className="container max-w-6xl">
        <div className="mb-10 text-center md:text-left">
          <h1 className="text-3xl md:text-4xl font-bold font-serif text-primary mb-2">My Wishlist</h1>
          <p className="text-sm text-muted-foreground">Saved luxury arrangements and handcrafted gifts</p>
        </div>

        {wishlistItems.length === 0 ? (
          <Card className="p-16 text-center border-border shadow-sm rounded-3xl">
            <div className="inline-flex h-20 w-20 items-center justify-center rounded-full bg-secondary/10 text-secondary mb-6">
              <Heart className="h-10 w-10" />
            </div>
            <h2 className="text-2xl font-bold font-serif text-primary mb-2">Your Wishlist is Empty</h2>
            <p className="text-muted-foreground text-sm mb-6 max-w-md mx-auto">
              Save your favorite arrangements while browsing so you can easily review or purchase them later.
            </p>
            <Link to="/products">
              <Button size="lg" variant="secondary" className="rounded-full font-bold px-8 gold-glow shadow-md">
                Browse Collections
              </Button>
            </Link>
          </Card>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            {wishlistItems.map((item) => {
              const p = item.products ? {
                id: item.product_id || item.id,
                name: item.products.name,
                price: item.products.price,
                image: item.products.image_url || item.products.image,
                slug: item.products.slug
              } : item;

              return (
                <Card key={item.id || p.id} className="overflow-hidden rounded-2xl border-border/80 luxury-shadow flex flex-col h-full group">
                  <Link to={`/product/${p.slug}`}>
                    <div className="aspect-square relative bg-muted/10 overflow-hidden">
                      <img
                        src={p.image}
                        alt={p.name}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                      />
                    </div>
                  </Link>
                  <div className="p-5 flex flex-col flex-1">
                    <Link to={`/product/${p.slug}`}>
                      <h3 className="font-bold text-base font-serif text-primary mb-2 line-clamp-1 hover:text-secondary transition-colors">
                        {p.name}
                      </h3>
                    </Link>
                    <p className="text-xl font-bold text-primary mb-4">
                      {formatINR(p.price)}
                    </p>
                    <div className="flex gap-2 mt-auto">
                      <Button
                        className="flex-1 rounded-full font-bold text-xs bg-primary hover:bg-secondary hover:text-primary transition-all"
                        size="sm"
                        onClick={() => handleAddToCart(item)}
                      >
                        <ShoppingBag className="h-3.5 w-3.5 mr-1.5" />
                        Add to Bag
                      </Button>
                      <Button
                        variant="outline"
                        size="icon"
                        className="rounded-full text-muted-foreground hover:text-destructive hover:border-destructive transition-colors shrink-0"
                        onClick={() => handleRemove({ id: p.id, name: p.name })}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </Card>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
