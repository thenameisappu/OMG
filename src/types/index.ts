export interface Option {
  label: string;
  value: string;
  icon?: React.ComponentType<{ className?: string }>;
  withCount?: boolean;
}

export interface Product {
  id: string;
  name: string;
  slug: string;
  description: string;
  price: number;
  category: string;
  image: string;
  hover_image?: string;
  features?: string[];
  is_featured?: boolean;
  stock_status: 'in_stock' | 'out_of_stock';
}

export interface CartItem extends Product {
  quantity: number;
}

export interface Order {
  id?: string;
  customerName: string;
  customerEmail: string;
  customerPhone: string;
  items: CartItem[];
  totalAmount: number;
  paymentMethod: string;
  deliveryOption: string;
  deliveryDate: string;
  deliveryTimeSlot?: string;
  deliveryAddress: string;
  status?: string;
}

