import { useState } from 'react';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Search, HelpCircle, Phone, Mail, Sparkles, MessageCircle } from "lucide-react";

export default function FAQ() {
  const [searchQuery, setSearchQuery] = useState('');

  const faqs = [
    {
      category: "Delivery",
      question: "What are your delivery hours in Bangalore?",
      answer: "We offer flexible daily delivery slots: Morning (09:00 - 12:00), Afternoon (13:00 - 17:00), Evening (18:00 - 21:00), and Midnight Surprise (00:00). Same-day delivery is available for orders placed before 2 PM."
    },
    {
      category: "Delivery",
      question: "Do you deliver to all areas within Bangalore?",
      answer: "Yes! We cover all areas across Bangalore including Indiranagar, Koramangala, Whitefield, HSR Layout, Jayanagar, Electronic City, Yelahanka, MG Road, and Sarjapur Road."
    },
    {
      category: "Surprises",
      question: "How does the Midnight Surprise service work?",
      answer: "Our Midnight Surprise team delivers your luxury floral arrangement, cake, or live acoustic serenade at exactly 12:00 AM. Advance booking is recommended to guarantee your preferred slot."
    },
    {
      category: "Quality",
      question: "How fresh are the flowers used by OMG?",
      answer: "All blooms are sourced daily from premium local and international farms. They are hand-selected by our florists and transported in temperature-regulated vehicles for 7+ day vase life."
    },
    {
      category: "Orders",
      question: "Can I add a custom handwritten message card?",
      answer: "Yes, every order includes a complimentary wax-sealed handwritten message card. You can write your custom message during checkout."
    },
    {
      category: "Customisation",
      question: "Do you offer custom arrangements and proposal styling?",
      answer: "Yes! Visit our 'Oh My Customisation's' section or 'Plan a Surprise' page. Our senior surprise architects design bespoke setups, romantic rose walkways, and event decor."
    },
    {
      category: "Delivery",
      question: "How do I track my delivery status?",
      answer: "Once dispatched, you'll receive real-time SMS and WhatsApp updates with driver tracking link. You can also view status under 'My Orders'."
    },
    {
      category: "Support",
      question: "What if I need to change the delivery address or time?",
      answer: "You can modify delivery details up to 4 hours before dispatch by contacting our 24/7 Concierge Hotline at +91 8147736396."
    }
  ];

  const filteredFaqs = faqs.filter(f =>
    f.question.toLowerCase().includes(searchQuery.toLowerCase()) ||
    f.answer.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <div className="min-h-screen bg-white">
      {/* Header Banner */}
      <div className="bg-primary text-white py-20 relative overflow-hidden">
        <div className="container relative z-10 text-center max-w-3xl space-y-4">
          <div className="inline-flex items-center gap-2 px-4 py-1 rounded-full bg-secondary/20 text-secondary text-xs font-bold uppercase tracking-widest border border-secondary/30">
            <HelpCircle className="h-4 w-4" /> Concierge Knowledge Base
          </div>
          <h1 className="text-4xl md:text-6xl font-bold font-serif">Frequently Asked Questions</h1>
          <p className="text-white/80 text-base font-light">
            Everything you need to know about our luxury arrangements, express delivery, and secret surprise planning.
          </p>

          <div className="pt-4 max-w-md mx-auto relative">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search questions (e.g. delivery, midnight, fresh)..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="h-12 pl-11 bg-white text-foreground rounded-full border-none shadow-lg text-sm"
            />
          </div>
        </div>
      </div>

      <div className="container max-w-4xl py-16">
        <Accordion type="single" collapsible className="space-y-4">
          {filteredFaqs.map((faq, index) => (
            <AccordionItem key={index} value={`item-${index}`} className="border border-border/80 rounded-2xl px-6 bg-white luxury-shadow transition-all hover:border-secondary/40">
              <AccordionTrigger className="text-left font-serif font-bold text-lg hover:text-secondary py-5">
                {faq.question}
              </AccordionTrigger>
              <AccordionContent className="text-muted-foreground text-sm leading-relaxed pb-6">
                {faq.answer}
              </AccordionContent>
            </AccordionItem>
          ))}
        </Accordion>

        {filteredFaqs.length === 0 && (
          <div className="text-center py-12">
            <p className="text-muted-foreground">No questions found matching "{searchQuery}".</p>
          </div>
        )}

        {/* Concierge Support Box */}
        <div className="mt-16 p-10 bg-primary text-white rounded-3xl border border-secondary/30 shadow-2xl flex flex-col md:flex-row items-center justify-between gap-8 relative overflow-hidden">
          <div className="space-y-2">
            <span className="text-secondary font-bold text-xs uppercase tracking-widest flex items-center gap-1.5">
              <Sparkles className="h-4 w-4" /> 24/7 Specialist Assistance
            </span>
            <h3 className="text-2xl md:text-3xl font-bold font-serif text-white">Need Personal Guidance?</h3>
            <p className="text-white/70 text-sm max-w-md">
              Our surprise architects and floral concierges are available 24/7 to assist with your order.
            </p>
          </div>

          <div className="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
            <a href="tel:+918147736396">
              <Button variant="secondary" className="h-14 px-8 rounded-full font-bold text-sm shadow-md gold-glow hover-lift w-full sm:w-auto">
                <Phone className="mr-2 h-4 w-4" /> Call +91 8147736396
              </Button>
            </a>
            <a href="mailto:info@ohmygudness.in">
              <Button variant="outline" className="h-14 px-6 rounded-full font-bold text-sm border-white/30 text-white hover:bg-white/10 w-full sm:w-auto">
                <Mail className="mr-2 h-4 w-4 text-secondary" /> Email Support
              </Button>
            </a>
          </div>
        </div>
      </div>
    </div>
  );
}
