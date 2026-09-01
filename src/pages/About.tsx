import { Flower2, Gift, HeartHandshake, Sparkles } from 'lucide-react';

const values = [
  {
    icon: Flower2,
    title: 'Our Story',
    description: 'Add the story behind OMG and how the brand began.',
  },
  {
    icon: Gift,
    title: 'What We Create',
    description: 'Add a short introduction to your flowers, gifts, and experiences.',
  },
  {
    icon: HeartHandshake,
    title: 'Our Promise',
    description: 'Add the promise or commitment you make to every customer.',
  },
];

const processSteps = [
  'Discover the collection',
  'Choose something meaningful',
  'Let us prepare the moment',
];

export default function About() {
  return (
    <main className="min-h-screen bg-background">
      <section className="relative overflow-hidden bg-primary px-6 py-24 text-white md:py-32">
        <div className="container relative z-10 mx-auto max-w-4xl text-center">
          <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-secondary/30 bg-secondary/10 px-4 py-1.5 text-xs font-bold uppercase tracking-[0.2em] text-secondary">
            <Sparkles className="h-4 w-4" /> About OMG
          </div>
          <h1 className="font-serif text-4xl font-bold leading-tight md:text-6xl">
            Add your brand story here
          </h1>
          <p className="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-white/75 md:text-lg">
            Add the introduction you would like visitors to read when they discover Oh My Gudness.
          </p>
        </div>
        <div className="absolute -bottom-24 left-1/2 h-48 w-[120%] -translate-x-1/2 rounded-[50%] border-t border-secondary/20 bg-background/10" />
      </section>

      <section className="container mx-auto max-w-5xl px-6 py-20 md:py-28">
        <div className="grid gap-12 md:grid-cols-[0.8fr_1.2fr] md:items-start">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.2em] text-secondary">The heart of OMG</p>
            <h2 className="mt-3 font-serif text-3xl font-bold text-primary md:text-4xl">
              Add your story heading
            </h2>
          </div>
          <div className="space-y-5 text-sm leading-7 text-muted-foreground md:text-base">
            <p>Add the main About content here once it is ready.</p>
            <p>Add a second paragraph for your mission, inspiration, or the people behind the brand.</p>
          </div>
        </div>
      </section>

      <section className="border-y border-border bg-muted/20">
        <div className="container mx-auto max-w-5xl px-6 py-20 md:py-24">
          <div className="mb-10 max-w-2xl">
            <p className="text-xs font-bold uppercase tracking-[0.2em] text-secondary">What matters to us</p>
            <h2 className="mt-3 font-serif text-3xl font-bold text-primary md:text-4xl">Our values</h2>
          </div>
          <div className="grid gap-5 md:grid-cols-3">
            {values.map(({ icon: Icon, title, description }) => (
              <article key={title} className="rounded-2xl border border-border bg-white p-7 shadow-sm">
                <Icon className="h-7 w-7 text-secondary" />
                <h3 className="mt-5 font-serif text-xl font-bold text-primary">{title}</h3>
                <p className="mt-3 text-sm leading-6 text-muted-foreground">{description}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="container mx-auto max-w-5xl px-6 py-20 md:py-28">
        <div className="grid gap-10 md:grid-cols-[1fr_1fr] md:items-center">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.2em] text-secondary">The OMG experience</p>
            <h2 className="mt-3 font-serif text-3xl font-bold text-primary md:text-4xl">From feeling to moment</h2>
            <p className="mt-5 max-w-lg text-sm leading-7 text-muted-foreground md:text-base">
              Add a short description of how your team turns a customer&apos;s idea into a thoughtful delivery.
            </p>
          </div>
          <ol className="space-y-4">
            {processSteps.map((step, index) => (
              <li key={step} className="flex items-center gap-4 rounded-xl border border-border bg-white p-4 shadow-sm">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-secondary">
                  {index + 1}
                </span>
                <span className="font-semibold text-primary">{step}</span>
              </li>
            ))}
          </ol>
        </div>
      </section>
    </main>
  );
}
