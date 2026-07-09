import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";

export default function FAQ() {
  const faqs = [
    {
      question: "What are your delivery hours?",
      answer: "We offer flexible delivery slots: Morning (09:00 - 12:00), Afternoon (13:00 - 17:00), Evening (18:00 - 21:00), and Midnight Surprise (00:00). Same-day delivery is available for orders placed before 1 PM."
    },
    {
      question: "Do you deliver to all areas in Bangalore?",
      answer: "Yes, we deliver to all areas within Bangalore, Karnataka. Our delivery network covers the entire city to ensure your flowers reach their destination fresh and on time."
    },
    {
      question: "How do I track my order?",
      answer: "Once your order is confirmed, you'll receive a tracking link via email and SMS. You can use this link to track your delivery in real-time and know exactly when your gift will arrive."
    },
    {
      question: "What if the recipient is not available?",
      answer: "Our delivery team will attempt to contact the recipient. If they're unavailable, we'll leave a note and attempt redelivery. You can also provide special delivery instructions during checkout."
    },
    {
      question: "Can I change my delivery date after placing an order?",
      answer: "Yes, you can modify your delivery date up to 24 hours before the scheduled delivery time. Please contact our customer support team at +91 8147736396 or email info@ohmygudness.in."
    },
    {
      question: "What payment methods do you accept?",
      answer: "We accept credit/debit cards, digital wallets (UPI, Paytm, Google Pay), and cash on delivery for your convenience."
    },
    {
      question: "How fresh are the flowers?",
      answer: "All our flowers are sourced fresh daily from trusted suppliers. We use temperature-controlled delivery vehicles and professional handling to ensure your flowers arrive in perfect condition."
    },
    {
      question: "Can I add a personalized message?",
      answer: "Absolutely! During checkout, you can add a personalized message card that will be included with your gift at no extra charge."
    },
    {
      question: "What is the Midnight Surprise service?",
      answer: "Our Midnight Surprise service delivers your gift at exactly 12:00 AM, perfect for birthdays and anniversaries. This service requires advance booking and is available throughout Bangalore."
    },
    {
      question: "Do you offer custom arrangements?",
      answer: "Yes! Visit our 'Oh My Customisation's' section or contact our event specialists to create bespoke floral arrangements tailored to your specific requirements."
    },
    {
      question: "What if I'm not satisfied with my order?",
      answer: "Customer satisfaction is our priority. If you're not completely satisfied, please contact us within 24 hours of delivery at +91 8147736396 or info@ohmygudness.in, and we'll make it right."
    },
    {
      question: "Can I schedule a delivery for a future date?",
      answer: "Yes, you can schedule deliveries up to 30 days in advance. Simply select your preferred date and time slot during checkout."
    }
  ];

  return (
    <div className="min-h-screen bg-white py-16">
      <div className="container max-w-4xl">
        <h1 className="text-4xl md:text-5xl font-bold mb-4">Frequently Asked Questions</h1>
        <p className="text-lg text-muted-foreground mb-12">
          Find answers to common questions about our services
        </p>

        <Accordion type="single" collapsible className="space-y-4">
          {faqs.map((faq, index) => (
            <AccordionItem key={index} value={`item-${index}`} className="border rounded-lg px-6">
              <AccordionTrigger className="text-left font-semibold hover:text-secondary">
                {faq.question}
              </AccordionTrigger>
              <AccordionContent className="text-muted-foreground">
                {faq.answer}
              </AccordionContent>
            </AccordionItem>
          ))}
        </Accordion>

        <div className="mt-12 p-8 bg-secondary/10 rounded-2xl border border-secondary/20">
          <h3 className="text-2xl font-bold mb-4">Still have questions?</h3>
          <p className="text-muted-foreground mb-4">
            Our customer support team is here to help you.
          </p>
          <div className="space-y-2">
            <p className="font-semibold">📞 Phone: +91 8147736396</p>
            <p className="font-semibold">✉️ Email: info@ohmygudness.in</p>
            <p className="text-sm text-muted-foreground">Available 24/7 for your convenience</p>
          </div>
        </div>
      </div>
    </div>
  );
}
