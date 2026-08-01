import { useState, useEffect } from "react";
import { useSearchParams } from "react-router-dom";
import { Shield, FileText, RefreshCw, Package, Truck } from "lucide-react";
import { SITE_URL } from "@/config";

const tabs = [
  { id: "terms", label: "Terms & Conditions", icon: FileText },
  { id: "privacy", label: "Privacy Policy", icon: Shield },
  { id: "refund", label: "Refund & Cancellation", icon: RefreshCw },
  { id: "return", label: "Return Policy", icon: Package },
  { id: "shipping", label: "Shipping Policy", icon: Truck },
];

function Section({ title, children }: { title?: string; children: React.ReactNode }) {
  return (
    <div className="mb-8">
      {title && (
        <h2 className="text-xl font-bold text-foreground mb-3 pb-2 border-b border-secondary/20">
          {title}
        </h2>
      )}
      <div className="text-muted-foreground leading-relaxed space-y-3">{children}</div>
    </div>
  );
}

function NumberedList({ items }: { items: React.ReactNode[] }) {
  return (
    <ol className="list-decimal list-outside ml-5 space-y-3">
      {items.map((item, i) => (
        <li key={i} className="text-muted-foreground leading-relaxed">
          {item}
        </li>
      ))}
    </ol>
  );
}

function TermsContent() {
  return (
    <div>
      <Section>
        <p>
          This document is an electronic record in terms of Information Technology Act, 2000 and rules
          thereunder as applicable and the amended provisions pertaining to electronic records in various
          statutes as amended by the Information Technology Act, 2000. This electronic record is generated
          by a computer system and does not require any physical or digital signatures.
        </p>
        <p>
          This document is published in accordance with the provisions of Rule 3(1) of the Information
          Technology (Intermediaries guidelines) Rules, 2011 that require publishing the rules and
          regulations, privacy policy and Terms of Use for access or usage of domain name{" "}
          <a href={SITE_URL || "#"} className="text-secondary hover:underline font-medium">
            {SITE_URL || "https://ohmygudness.in"}
          </a>{" "}
          ("Website"), including the related mobile site and mobile application (hereinafter referred to as "Platform").
        </p>
        <p>
          The Platform is owned by <strong className="text-foreground">Oh My Gudness</strong>, a company
          incorporated under the Companies Act, 1956 with its registered office at 1916, 2nd floor,
          31st cross, Banashankari 2nd stage, Bangalore - 560070 (hereinafter referred to as "Platform
          Owner", "we", "us", "our").
        </p>
        <p>
          Your use of the Platform and services and tools are governed by the following terms and
          conditions ("Terms of Use") as applicable to the Platform including the applicable policies
          which are incorporated herein by way of reference. By mere use of the Platform, You shall be
          contracting with the Platform Owner and these terms and conditions including the policies
          constitute Your binding obligations, with Platform Owner.
        </p>
        <p>
          These Terms of Use can be modified at any time without assigning any reason. It is your
          responsibility to periodically review these Terms of Use to stay informed of updates.
        </p>
        <p>
          For the purpose of these Terms of Use, wherever the context so requires "you", "your" or
          "user" shall mean any natural or legal person who has agreed to become a user/buyer on the Platform.
        </p>
      </Section>

      <Section>
        <div className="bg-secondary/10 border border-secondary/30 rounded-xl p-5">
          <p className="font-semibold text-foreground uppercase tracking-wide text-sm">
            ACCESSING, BROWSING OR OTHERWISE USING THE PLATFORM INDICATES YOUR AGREEMENT TO ALL THE TERMS
            AND CONDITIONS UNDER THESE TERMS OF USE, SO PLEASE READ THE TERMS OF USE CAREFULLY BEFORE PROCEEDING.
          </p>
        </div>
      </Section>

      <Section title="Use of the Platform">
        <p>The use of Platform and/or availing of our Services is subject to the following Terms of Use:</p>
        <NumberedList
          items={[
            "To access and use the Services, you agree to provide true, accurate and complete information to us during and after registration, and you shall be responsible for all acts done through the use of your registered account on the Platform.",
            "Neither we nor any third parties provide any warranty or guarantee as to the accuracy, timeliness, performance, completeness or suitability of the information and materials offered on this website or through the Services. We expressly exclude liability for any such inaccuracies or errors to the fullest extent permitted by law.",
            "Your use of our Services and the Platform is solely and entirely at your own risk and discretion. You are required to independently assess and ensure that the Services meet your requirements.",
            "The contents of the Platform and the Services are proprietary to us and are licensed to us. You will not have any authority to claim any intellectual property rights, title, or interest in its contents.",
            "You acknowledge that unauthorized use of the Platform and/or the Services may lead to action against you as per these Terms of Use and/or applicable laws.",
            "You agree to pay us the charges associated with availing the Services.",
            "You agree not to use the Platform and/or Services for any purpose that is unlawful, illegal or forbidden by these Terms, or Indian or local laws that might apply to you.",
            "You agree and acknowledge that website and the Services may contain links to other third party websites. On accessing these links, you will be governed by the terms of use, privacy policy and such other policies of such third party websites.",
            "You understand that upon initiating a transaction for availing the Services you are entering into a legally binding and enforceable contract with the Platform Owner for the Services.",
            "You shall indemnify and hold harmless Platform Owner, its affiliates, group companies and their respective officers, directors, agents, and employees, from any claim or demand made by any third party or penalty imposed due to or arising out of Your breach of this Terms of Use or Your violation of any law, rules or regulations.",
            "Notwithstanding anything contained in these Terms of Use, the parties shall not be liable for any failure to perform an obligation under these Terms if performance is prevented or delayed by a force majeure event.",
            "These Terms and any dispute or claim relating to it, or its enforceability, shall be governed by and construed in accordance with the laws of India.",
            "All disputes arising out of or in connection with these Terms shall be subject to the exclusive jurisdiction of the courts in Bangalore, India.",
            "All concerns or communications relating to these Terms must be communicated to us using the contact information provided on this website.",
          ]}
        />
      </Section>
    </div>
  );
}

function PrivacyContent() {
  return (
    <div>
      <Section title="Introduction">
        <p>
          This Privacy Policy describes how{" "}
          <a href={SITE_URL || "#"} className="text-secondary hover:underline font-medium">
            {SITE_URL || "https://ohmygudness.in"}
          </a>{" "}
          - <strong className="text-foreground">Oh My Gudness</strong> and its affiliates (collectively
          "Oh My Gudness, we, our, us") collect, use, share, protect or otherwise process your
          information/personal data through our Platform.
        </p>
        <p>
          We do not offer any product/service under this Platform outside India and your personal data
          will primarily be stored and processed in India. By visiting this Platform, you expressly agree
          to be bound by the terms and conditions of this Privacy Policy.
        </p>
      </Section>

      <Section title="Collection">
        <p>
          We collect your personal data when you use our Platform, services or otherwise interact with
          us during the course of our relationship. Some of the information we may collect includes but
          is not limited to: name, date of birth, address, telephone/mobile number, email ID and/or any
          such information shared as proof of identity or address.
        </p>
        <p>
          Some of the sensitive personal data may be collected with your consent, such as your bank
          account or credit/debit card or other payment instrument information.
        </p>
        <p>
          If you receive a call from a person claiming to be Oh My Gudness seeking personal data like
          debit/credit card PIN or mobile banking password, please never provide such information. Report
          it immediately to an appropriate law enforcement agency if already revealed.
        </p>
      </Section>

      <Section title="Usage">
        <p>
          We use personal data to provide the services you request and to assist sellers and business
          partners in handling and fulfilling orders; enhancing customer experience; to resolve disputes;
          troubleshoot problems; inform you about online and offline offers, products, services, and
          updates; customise your experience; detect and protect us against error, fraud and other
          criminal activity; enforce our terms and conditions; conduct marketing research, analysis and
          surveys.
        </p>
      </Section>

      <Section title="Sharing">
        <p>
          We may share your personal data internally within our group entities, our other corporate
          entities, and affiliates to provide you access to the services and products offered by them.
          These entities and affiliates may market to you as a result of such sharing unless you
          explicitly opt-out.
        </p>
        <p>
          We may disclose personal data to third parties such as sellers, business partners, third party
          service providers including logistics partners, prepaid payment instrument issuers, third-party
          reward programs and other payment opted by you.
        </p>
        <p>
          We may disclose personal and sensitive personal data to government agencies or other authorised
          law enforcement agencies if required to do so by law or in the good faith belief that such
          disclosure is reasonably necessary to respond to subpoenas, court orders, or other legal process.
        </p>
      </Section>

      <Section title="Security Precautions">
        <p>
          To protect your personal data from unauthorised access or disclosure, loss or misuse we adopt
          reasonable security practices and procedures. Once your information is in our possession, we
          adhere to our security guidelines to protect it against unauthorised access and offer the use
          of a secure server.
        </p>
        <p>
          However, the transmission of information is not completely secure for reasons beyond our
          control. Users are responsible for ensuring the protection of login and password records for
          their account.
        </p>
      </Section>

      <Section title="Data Deletion and Retention">
        <p>
          You have an option to delete your account by visiting your profile and settings on our
          Platform. This action would result in you losing all information related to your account.
          You may also write to us at the contact information provided below.
        </p>
        <p>
          We retain your personal data information for a period no longer than is required for the
          purpose for which it was collected or as required under any applicable law. We may continue
          to retain your data in anonymised form for analytical and research purposes.
        </p>
      </Section>

      <Section title="Your Rights">
        <p>
          You may access, rectify, and update your personal data directly through the functionalities
          provided on the Platform.
        </p>
      </Section>

      <Section title="Consent">
        <p>
          By visiting our Platform or by providing your information, you consent to the collection, use,
          storage, disclosure and otherwise processing of your information on the Platform in accordance
          with this Privacy Policy.
        </p>
        <p>
          You have an option to withdraw your consent by writing to the Grievance Officer at the contact
          information provided below. Please mention "Withdrawal of consent for processing personal data"
          in your subject line. Please note that your withdrawal of consent will not be retrospective.
        </p>
      </Section>

      <Section title="Changes to this Privacy Policy">
        <p>
          Please check our Privacy Policy periodically for changes. We may update this Privacy Policy to
          reflect changes to our information practices and may alert/notify you about significant changes
          in the manner required under applicable laws.
        </p>
      </Section>

      <Section title="Grievance Officer">
        <div className="bg-secondary/10 border border-secondary/20 rounded-xl p-5 space-y-2">
          <p><strong className="text-foreground">Company:</strong> Oh My Gudness</p>
          <p><strong className="text-foreground">Address:</strong> 1916, 2nd floor, 31st cross, Banashankari 2nd stage, Bangalore - 560070</p>
          <p>
            <strong className="text-foreground">Email:</strong>{" "}
            <a href="mailto:info@ohmygudness.in" className="text-secondary hover:underline">
              info@ohmygudness.in
            </a>
          </p>
          <p>
            <strong className="text-foreground">Phone:</strong>{" "}
            <a href="tel:+918147736396" className="text-secondary hover:underline">
              +91 8147736396
            </a>
          </p>
        </div>
      </Section>
    </div>
  );
}

function RefundContent() {
  return (
    <div>
      <Section>
        <p>
          This refund and cancellation policy outlines how you can cancel or seek a refund for a
          product/service that you have purchased through the Platform.
        </p>
      </Section>

      <Section title="Cancellation Terms">
        <NumberedList
          items={[
            <span>Cancellations will only be considered if the request is made within <strong className="text-foreground">3 days</strong> of placing the order. However, cancellation requests may not be entertained if the orders have been communicated to the sellers/merchant(s) and they have initiated the process of shipping them, or the product is out for delivery. In such an event, you may choose to reject the product at the doorstep.</span>,
            "Oh My Gudness does not accept cancellation requests for perishable items like flowers, eatables, etc. However, the refund/replacement can be made if the user establishes that the quality of the product delivered is not good.",
            <span>In case of receipt of damaged or defective items, please report to our customer service team. This should be reported within <strong className="text-foreground">3 days</strong> of receipt of products. The request would be entertained once the seller/merchant has checked and determined the same at its own end.</span>,
            <span>In case you feel that the product received is not as shown on the site or as per your expectations, you must bring it to the notice of our customer service within <strong className="text-foreground">3 days</strong> of receiving the product. The customer service team after looking into your complaint will take an appropriate decision.</span>,
            "In case of complaints regarding the products that come with a warranty from the manufacturers, please refer the issue to them.",
            <span>In case of any refunds approved by Oh My Gudness, it will take <strong className="text-foreground">3 days</strong> for the refund to be processed to you.</span>,
          ]}
        />
      </Section>

      <Section>
        <div className="bg-secondary/10 border border-secondary/20 rounded-xl p-5">
          <h3 className="font-semibold text-foreground mb-2">Need Help?</h3>
          <p>Contact our customer service team for any refund or cancellation queries:</p>
          <div className="mt-3 space-y-1">
            <p><a href="tel:+918147736396" className="text-secondary hover:underline font-medium">+91 8147736396</a></p>
            <p><a href="mailto:[info@ohmygudness.in]" className="text-secondary hover:underline font-medium">info@ohmygudness.in</a></p>
          </div>
        </div>
      </Section>
    </div>
  );
}

function ReturnContent() {
  return (
    <div>
      <Section>
        <p>
          We offer refund/exchange within the first{" "}
          <strong className="text-foreground">1 day</strong> from the date of your purchase. If 1 day
          has passed since your purchase, you will not be offered a return, exchange or refund of any kind.
        </p>
      </Section>

      <Section title="Eligibility for Return / Exchange">
        <p>In order to become eligible for a return or an exchange:</p>
        <ul className="list-disc list-outside ml-5 space-y-2">
          <li>The purchased item should be unused and in the same condition as you received it.</li>
          <li>The item must have original packaging.</li>
          <li>If the item was purchased on sale, then the item may not be eligible for a return/exchange.</li>
          <li>Only items found defective or damaged are replaced by us based on an exchange request.</li>
        </ul>
      </Section>

      <Section title="Exempted Products">
        <p>
          There may be a certain category of products/items that are exempted from returns or refunds.
          Such categories of the products would be identified to you at the time of purchase.
        </p>
      </Section>

      <Section title="Return / Exchange Process">
        <p>
          For exchange/return accepted requests, once your returned product/item is received and
          inspected by us, we will send you an email to notify you about receipt of the
          returned/exchanged product. If approved after quality check, your request will be processed
          in accordance with our policies.
        </p>
      </Section>

      <Section>
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-5">
          <p className="text-amber-800 font-medium">
            ⚠️ Please note: Return requests must be raised within{" "}
            <strong>1 day</strong> of purchase. Perishable items such as flowers and eatables are
            generally not eligible for returns but may qualify for replacement if quality issues are
            reported promptly.
          </p>
        </div>
      </Section>
    </div>
  );
}

function ShippingContent() {
  return (
    <div>
      <Section>
        <p>
          The orders for the user are shipped through registered domestic courier companies and/or speed
          post only. Orders are shipped within{" "}
          <strong className="text-foreground">3 days</strong> from the date of the order and/or payment
          or as per the delivery date agreed at the time of order confirmation, subject to courier
          company/post office norms.
        </p>
      </Section>

      <Section title="Important Shipping Information">
        <ul className="list-disc list-outside ml-5 space-y-3">
          <li>Platform Owner shall not be liable for any delay in delivery by the courier company/postal authority.</li>
          <li>Delivery of all orders will be made to the address provided by the buyer at the time of purchase.</li>
          <li>Delivery of our services will be confirmed on your email ID as specified at the time of registration.</li>
          <li>If there are any shipping cost(s) levied by the seller or the Platform Owner, the same is not refundable.</li>
        </ul>
      </Section>

      <Section>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="bg-secondary/10 border border-secondary/20 rounded-xl p-5 text-center">
            <h4 className="font-semibold text-foreground mb-1">Order Processing</h4>
            <p className="text-sm">Within 3 days of order placement</p>
          </div>
          <div className="bg-secondary/10 border border-secondary/20 rounded-xl p-5 text-center">
            <h4 className="font-semibold text-foreground mb-1">Registered Courier</h4>
            <p className="text-sm">Trusted domestic courier partners</p>
          </div>
          <div className="bg-secondary/10 border border-secondary/20 rounded-xl p-5 text-center">
            <h4 className="font-semibold text-foreground mb-1">Delivery Location</h4>
            <p className="text-sm">As per address given at checkout</p>
          </div>
        </div>
      </Section>

      <Section>
        <div className="bg-secondary/10 border border-secondary/20 rounded-xl p-5">
          <h3 className="font-semibold text-foreground mb-2">Shipping Queries</h3>
          <p>For any shipping-related queries, please contact our customer service team:</p>
          <div className="mt-3 space-y-1">
            <p><a href="tel:+918147736396" className="text-secondary hover:underline font-medium">+91 8147736396</a></p>
            <p><a href="mailto:[info@ohmygudness.in]" className="text-secondary hover:underline font-medium">info@ohmygudness.in</a></p>
          </div>
        </div>
      </Section>
    </div>
  );
}

export default function TermsAndConditions() {
  const [searchParams, setSearchParams] = useSearchParams();
  const tabParam = searchParams.get("tab");
  const [activeTab, setActiveTab] = useState(tabParam || "terms");

  useEffect(() => {
    if (tabParam && tabs.find((t) => t.id === tabParam)) {
      setActiveTab(tabParam);
    }
  }, [tabParam]);

  const handleTabChange = (tabId: string) => {
    setActiveTab(tabId);
    setSearchParams({ tab: tabId });
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const renderContent = () => {
    switch (activeTab) {
      case "terms": return <TermsContent />;
      case "privacy": return <PrivacyContent />;
      case "refund": return <RefundContent />;
      case "return": return <ReturnContent />;
      case "shipping": return <ShippingContent />;
      default: return <TermsContent />;
    }
  };

  const activeTabData = tabs.find((t) => t.id === activeTab);

  return (
    <div className="min-h-screen bg-white">
      <div className="bg-primary py-14 text-primary-foreground">
        <div className="container max-w-5xl">
          <h1 className="text-4xl md:text-5xl font-bold mb-3">Legal &amp; Policies</h1>
          <p className="text-muted-foreground max-w-2xl">
            Please read our policies carefully. By using our platform, you agree to be bound by these terms.
          </p>
          <p className="text-xs text-muted-foreground mt-2 opacity-70">
            Last updated: January 2026 &nbsp;|&nbsp; Oh My Gudness, Bangalore - 560070
          </p>
        </div>
      </div>

      <div className="container max-w-5xl py-10">
        <div className="overflow-x-auto scrollbar-hide -mx-4 px-4 mb-10">
          <div className="flex gap-2 min-w-max">
            {tabs.map((tab) => {
              const Icon = tab.icon;
              const isActive = activeTab === tab.id;
              return (
                <button
                  key={tab.id}
                  onClick={() => handleTabChange(tab.id)}
                  className={`flex items-center gap-2 px-5 py-3 rounded-full text-sm font-medium transition-all whitespace-nowrap border ${isActive
                    ? "bg-secondary text-secondary-foreground border-secondary shadow-md scale-105"
                    : "bg-white text-muted-foreground border-border hover:border-secondary/50 hover:text-secondary hover:bg-secondary/5"
                    }`}
                >
                  <Icon className="h-4 w-4" />
                  {tab.label}
                </button>
              );
            })}
          </div>
        </div>

        <div className="bg-white border border-border rounded-2xl shadow-sm p-8 md:p-12">
          {activeTabData && (
            <div className="flex items-center gap-3 mb-8">
              <div className="bg-secondary/10 p-3 rounded-xl">
                <activeTabData.icon className="h-6 w-6 text-secondary" />
              </div>
              <h2 className="text-2xl font-bold text-foreground">{activeTabData.label}</h2>
            </div>
          )}
          <div className="max-w-none">{renderContent()}</div>
        </div>

        <div className="mt-8 p-6 bg-secondary/10 rounded-2xl border border-secondary/20 text-center">
          <p className="text-muted-foreground text-sm">
            Have questions about our policies?{" "}
            <a href="mailto:info@ohmygudness.in" className="text-secondary font-semibold hover:underline">
              info@ohmygudness.in
            </a>{" "}
            or call us at{" "}
            <a href="tel:+918147736396" className="text-secondary font-semibold hover:underline">
              +91 8147736396
            </a>
          </p>
        </div>
      </div>
    </div>
  );
}
