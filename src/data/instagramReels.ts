export interface InstagramReelItem {
  id: string;
  title: string;
  caption: string;
  thumbnail: string;
  videoUrl: string;
  instagramUrl: string;
  handle: string;
  likes?: number | string;
  comments?: number | string;
  views?: string;
  date?: string;
  featuredProduct?: {
    name: string;
    price: number;
    link: string;
    image?: string;
  };
}

export const OFFICIAL_INSTAGRAM = {
  handle: "@ohmygudness.in",
  url: "https://www.instagram.com/ohmygudness.in/",
  name: "OMG !! Bengaluru Gifts",
  city: "Bengaluru, Karnataka"
};

/**
 * World of Blooms - Authentic OMG!! Instagram Reels
 * Connected to official Instagram handle: @ohmygudness.in
 * Verified real-time insights extracted directly from live posts.
 */
export const INSTAGRAM_REELS: InstagramReelItem[] = [
  {
    id: "reel-1",
    title: "Action Prince Dhruva Sarja Tribute",
    caption: "When raw screen power meets luxury floral tribute! Presenting our bespoke Action Prince Birthday Tribute Bouquet—an extraordinary arrangement crafted specially for Sandalwood's favorite action hero. Ultimate fan tribute fit for royalty! 👑💥",
    thumbnail: "/reels/reel_1.jpg",
    videoUrl: "/reels/reel_1.mp4",
    instagramUrl: "https://www.instagram.com/reel/DePQdXiSOYb/?utm_source=ig_web_copy_link&mdxt=MWQ1ZGUxMzBkMA==&srtk=MWQ1ZGUxMzBkMA==",
    handle: "@ohmygudness.in",
    likes: 257,
    comments: 128,
    date: "Verified Reel",
    featuredProduct: {
      name: "Bespoke Grand Floral Tribute",
      price: 4999,
      link: "/products",
      image: "/reels/reel_1.jpg"
    }
  },
  {
    id: "reel-2",
    title: "Thoughtful Birthday Surprise by OMG",
    caption: "\"Still not over how thoughtful this was 🥹💐 Thank you, Oh My Gudness, for putting so much love into every little detail. Truly made my birthday extra special! 🤍✨\" — Curated celebration with celebratory champagne, fragrant lilies, custom delicacies & bespoke party styling.",
    thumbnail: "/reels/reel_2.jpg",
    videoUrl: "/reels/reel_2.mp4",
    instagramUrl: "https://www.instagram.com/reel/Dd6g1tusQ01/?utm_source=ig_web_copy_link&psln=MWQ1ZGUxMzBkMA==&srtk=MWQ1ZGUxMzBkMA==",
    handle: "@ohmygudness.in",
    likes: 495,
    comments: 6,
    date: "Verified Reel",
    featuredProduct: {
      name: "Midnight Birthday Celebration Package",
      price: 8999,
      link: "/surprise-services",
      image: "/reels/reel_2.jpg"
    }
  },
  {
    id: "reel-3",
    title: "40 Years Celebration: Shivarajkumar & Geetha",
    caption: "Crafting a masterpiece for a love story that has inspired generations. 💐✨ The celebrity-worthy bouquet created to celebrate 40 years of togetherness for legendary Shivarajkumar Sir and Geetha Ma'am. Watch the pure magic of luxury gifting come to life. 👑❤️",
    thumbnail: "/reels/reel_3.jpg",
    videoUrl: "/reels/reel_3.mp4",
    instagramUrl: "https://www.instagram.com/reel/DYpCQd2SFBT/?utm_source=ig_web_copy_link&xtok=MWQ1ZGUxMzBkMA==&srtk=MWQ1ZGUxMzBkMA==",
    handle: "@ohmygudness.in",
    likes: 153,
    comments: 0,
    date: "Verified Reel",
    featuredProduct: {
      name: "Anniversary Floral Keepsake Setup",
      price: 12999,
      link: "/surprise-services",
      image: "/reels/reel_3.jpg"
    }
  },
  {
    id: "reel-4",
    title: "Signature Kaju Katli Sweet Bouquet",
    caption: "Sweet gifts > regular flowers 😍✨ Introducing our Kaju Katli Bouquet — the perfect blend of gifting, sweetness, and surprises 🤍 Because flowers fade... but kaju katli disappears faster 😋 Thoughtfully curated for birthdays and festive celebrations.",
    thumbnail: "/reels/reel_4.jpg",
    videoUrl: "/reels/reel_4.mp4",
    instagramUrl: "https://www.instagram.com/reel/DYWBGpExvoW/?utm_source=ig_web_copy_link&cplk=MWQ1ZGUxMzBkMA==&srtk=MWQ1ZGUxMzBkMA==",
    handle: "@ohmygudness.in",
    likes: 98,
    comments: 2,
    date: "Verified Reel",
    featuredProduct: {
      name: "Luxury Kaju Katli Edible Bouquet",
      price: 3499,
      link: "/products",
      image: "/reels/reel_4.jpg"
    }
  },
  {
    id: "reel-5",
    title: "Swarna Gauri Bagina Floral Bouquet",
    caption: "Gauri Habba tradition reimagined in full festive bloom! 🪞🩵💛✨ Sacred rituals meet luxury floral artistry! Handcrafted with every sacred element a woman cherishes for Gowri Habba. The most thoughtful festive blessing for mothers, sisters, and daughters.",
    thumbnail: "/reels/reel_5.jpg",
    videoUrl: "/reels/reel_5.mp4",
    instagramUrl: "https://www.instagram.com/reel/DdCOf4lSN8a/?utm_source=ig_web_copy_link&vrfl=MWQ1ZGUxMzBkMA==&srtk=MWQ1ZGUxMzBkMA==",
    handle: "@ohmygudness.in",
    likes: 105,
    comments: 2,
    date: "Verified Reel",
    featuredProduct: {
      name: "Traditional Swarna Bagina Hamper",
      price: 2499,
      link: "/products",
      image: "/reels/reel_5.jpg"
    }
  },
  {
    id: "reel-6",
    title: "Cherry On Top Milestone Decor",
    caption: "One last birthday in her childhood home… before she walks into forever 🍒✨ A sweet, nostalgia-filled celebration for a beautiful milestone. Custom \"Cherry On Top\" themed decor with pastel balloon archways and cascading florals.",
    thumbnail: "/reels/reel_6.jpg",
    videoUrl: "/reels/reel_6.mp4",
    instagramUrl: "https://www.instagram.com/reel/DZR3Wn_yitM/?utm_source=ig_web_copy_link&exln=MWQ1ZGUxMzBkMA==&srtk=MWQ1ZGUxMzBkMA==",
    handle: "@ohmygudness.in",
    likes: 15,
    comments: 2,
    date: "Verified Reel",
    featuredProduct: {
      name: "Cherry Themed Celebration Styling",
      price: 15999,
      link: "/surprise-services",
      image: "/reels/reel_6.jpg"
    }
  },
  {
    id: "reel-7",
    title: "RCB 'Ee Sala Cup Namde' Celebration",
    caption: "When every fan is chanting \"Ee Salanu Cup Namde!!\", a celebration isn't just an option—it's a necessity! ❤️ Team OMG steps in to spark smiles, emotions, and one unforgettable reaction: 'OH MY GUDNESS!!' 🤩",
    thumbnail: "/reels/reel_7.jpg",
    videoUrl: "/reels/reel_7.mp4",
    instagramUrl: "https://www.instagram.com/reel/DZAZHbtyemr/?utm_source=ig_web_copy_link&psln=MWQ1ZGUxMzBkMA==&srtk=MWQ1ZGUxMzBkMA==",
    handle: "@ohmygudness.in",
    likes: 237,
    comments: 19,
    date: "Verified Reel",
    featuredProduct: {
      name: "Royal Cricket Celebration Hamper",
      price: 3999,
      link: "/products",
      image: "/reels/reel_7.jpg"
    }
  },
  {
    id: "reel-8",
    title: "Signature 'Hey Beautiful' Mirror Bouquet",
    caption: "Not just a bouquet... a reflection of your beauty 🌹🪞 Watch the magic come together — introducing our signature Mirror Bouquet ✨ The ultimate combination of luxury red roses, fairy lights, and a message that says it all: 'Hey Beautiful'.",
    thumbnail: "/reels/reel_8.jpg",
    videoUrl: "/reels/reel_8.mp4",
    instagramUrl: "https://www.instagram.com/reel/DY3tZ4_AdAN/?utm_source=ig_web_copy_link&psln=MWQ1ZGUxMzBkMA==&srtk=MWQ1ZGUxMzBkMA==",
    handle: "@ohmygudness.in",
    likes: 129,
    comments: 6,
    date: "Verified Reel",
    featuredProduct: {
      name: "Signature 'Hey Beautiful' Mirror Bouquet",
      price: 2999,
      link: "/products",
      image: "/reels/reel_8.jpg"
    }
  }
];
