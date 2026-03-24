/**
 * Bagel Boyz NJ - Social Content Generator
 *
 * Generates AI-powered social media content for Facebook and Instagram.
 * Creates post text, AI food photography, and branded images.
 * Publishes to connected social accounts.
 *
 * Adapted from BoatLife.ai social-content-generator.js
 */

const path = require('path');
const fs = require('fs');
const { v4: uuidv4 } = require('uuid');
const { generateBrandedImages } = require('./brand-image-engine');
const { publishToFacebook, publishToInstagram } = require('./social-publisher');

// ─── Config ────────────────────────────────────────────────
const UPLOAD_DIR = path.join(__dirname, '../../uploads/social');

function ensureDir(dir) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

// ─── AI Client (OpenRouter) ────────────────────────────────
const axios = require('axios');

async function callAI(systemPrompt, userPrompt, { maxTokens = 1200 } = {}) {
  const apiKey = process.env.OPENROUTER_API_KEY || process.env.ANTHROPIC_API_KEY;
  const isOpenRouter = !!process.env.OPENROUTER_API_KEY;

  if (isOpenRouter) {
    const res = await axios.post('https://openrouter.ai/api/v1/chat/completions', {
      model: 'anthropic/claude-3.5-sonnet',
      max_tokens: maxTokens,
      messages: [
        { role: 'system', content: systemPrompt },
        { role: 'user', content: userPrompt }
      ]
    }, {
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json'
      }
    });
    return res.data.choices[0].message.content;
  } else {
    // Direct Anthropic API
    const Anthropic = require('anthropic');
    const client = new Anthropic({ apiKey });
    const msg = await client.messages.create({
      model: 'claude-sonnet-4-20250514',
      max_tokens: maxTokens,
      system: systemPrompt,
      messages: [{ role: 'user', content: userPrompt }]
    });
    return msg.content[0].text;
  }
}

// ─── Content Categories ────────────────────────────────────
const CONTENT_CATEGORIES = [
  {
    id: 'daily_special',
    name: 'Daily Special / Featured Item',
    prompt: 'Highlight a specific menu item, bagel variety, or cream cheese flavor. Make it mouth-watering and urgent — "today only" or "while they last" energy. Mention the specific item name and what makes it amazing.'
  },
  {
    id: 'behind_scenes',
    name: 'Behind the Scenes',
    prompt: 'Show the craft of bagel-making: dough being shaped, bagels boiling in the kettle, fresh trays coming out of the oven, cream cheese being mixed. Emphasize the early morning hustle and the care that goes into every batch.'
  },
  {
    id: 'nj_culture',
    name: 'NJ Bagel Culture',
    prompt: 'Tap into New Jersey bagel shop culture: the Taylor Ham vs Pork Roll debate, SPK (salt pepper ketchup), the morning rush ritual, what makes NJ bagels different from everywhere else, Jersey pride. Be authentic and fun.'
  },
  {
    id: 'customer_favorite',
    name: 'Customer Favorites / Social Proof',
    prompt: 'Highlight popular orders, customer reactions, or the community love. "Our most ordered sandwich," "What the regulars know," "The order that keeps people coming back." Make readers feel like they are missing out.'
  },
  {
    id: 'seasonal_trending',
    name: 'Seasonal / Trending',
    prompt: 'Connect to the current season, weather, holidays, or food trends. Cold morning = hot coffee + fresh bagel. Summer = iced coffee. Holidays = catering. Football season = game day platters. Back to school = quick breakfast.'
  },
  {
    id: 'community',
    name: 'Community / Local Love',
    prompt: 'Celebrate the Hazlet/Holmdel/Monmouth County community. Local events, supporting neighbors, being part of the neighborhood fabric. The family-owned angle — Robert and Jessica building something for their community.'
  },
  {
    id: 'menu_deep_dive',
    name: 'Menu Deep Dive',
    prompt: 'Go deep on one specific menu category: omelets, wraps, deli sandwiches, or sides. Describe the ingredients, preparation, or a specific combo order. Make people discover items they haven\'t tried yet.'
  },
  {
    id: 'bagel_science',
    name: 'Bagel Science / Process',
    prompt: 'Educate on the craft: the boil, the dough fermentation, high-gluten flour, malt barley, why NJ water matters (or doesn\'t), oven temperatures, seed application. Make food nerds and casual fans both interested.'
  },
  {
    id: 'morning_energy',
    name: 'Morning Energy / Routine',
    prompt: 'Capture the energy of a morning at Bagel Boyz: doors opening at 6 AM, first customers, coffee brewing, the smell hitting you when you walk in. Make people feel like they\'re there.'
  },
  {
    id: 'catering_spotlight',
    name: 'Catering Spotlight',
    prompt: 'Showcase catering for offices, parties, game days, holidays. Mention specific platters, wheels, hoagies. Plant the seed for people planning events.'
  }
];

// ─── Specific Topics Pool (rotated to prevent repetition) ──
// Each topic is a specific subject/angle that can only be used once per cycle
const TOPIC_POOL = [
  // Menu items
  'Taylor Ham Egg Cheese on everything with SPK',
  'Bacon Egg Cheese — the BEC',
  'Everything bagel with scallion cream cheese',
  'Western omelet with home fries',
  'The Works sandwich (TH + bacon + sausage)',
  'Sesame bagel with lox and cream cheese',
  'Jalapeño cheddar bagel',
  'French toast bagel with walnut raisin cream cheese',
  'Buffalo chicken wrap',
  'Italian sub on a bagel',
  'Philly cheesesteak',
  'Veggie egg and cheese',
  'Steak egg and cheese',
  'Greek omelet with feta and spinach',
  'Cinnamon raisin bagel toasted with butter',
  'Pumpernickel everything combo',
  'Gluten-free bagel options',
  'Bagel chips with cream cheese',
  'Iced coffee and a fresh bagel',
  'Hot chocolate on a cold morning',
  // Cream cheese flavors
  'Scallion cream cheese',
  'Jalapeño cream cheese',
  'Walnut raisin cream cheese',
  'Veggie cream cheese',
  'Sundried tomato cream cheese',
  'Lox spread cream cheese',
  // NJ Culture
  'Taylor Ham vs Pork Roll — the great debate',
  'What SPK means to Jersey people',
  'The art of the everything bagel',
  'Why NJ bagels are better than NY bagels',
  'The boil — what separates real bagels from bread circles',
  'NJ diner culture meets bagel shop culture',
  'The Jersey morning commute breakfast ritual',
  'Monmouth County food scene',
  // Behind the scenes
  'Bakers arriving before dawn',
  'Dough being hand-rolled and shaped',
  'Bagels hitting the boiling water',
  'Fresh trays coming out of the oven',
  'The cream cheese prep station',
  'The morning rush energy at the counter',
  'Boar\'s Head deli meats being sliced fresh',
  // Community
  'Hazlet neighborhood love',
  'Holmdel Rd location — the original',
  'Airport Plaza location — the expansion',
  'Feeding the local little league / sports teams',
  'Office catering hero stories',
  'Weekend family breakfast tradition',
  'The regulars who come every single day',
  // Catering
  'Bagel platter for the office',
  'Breakfast sandwich tray for a party',
  'Bagel wheels for game day',
  '30-inch hoagie for a crowd',
  'Nova lox platter for brunch',
  // Seasonal (will also use real date awareness)
  'Cold weather comfort breakfast',
  'Summer iced coffee energy',
  'Holiday catering season',
  'Back to school quick breakfast',
  'Weekend brunch vibes',
  'Rainy day bagel craving',
  'Spring morning fresh start',
];

// ─── Pick a specific topic (never repeat within last 21 posts) ──
function pickTopic() {
  const recentTopics = recentPosts.slice(0, 21).map(p => p.topic).filter(Boolean);
  const available = TOPIC_POOL.filter(t => !recentTopics.includes(t));
  // If we've exhausted the pool, reset (shouldn't happen with 60+ topics and 21 post window)
  const pool = available.length > 0 ? available : TOPIC_POOL;
  return pool[Math.floor(Math.random() * pool.length)];
}

// ─── Brand Voice System Prompt ─────────────────────────────
const BRAND_VOICE = `You are the social media voice of Bagel Boyz NJ — a family-owned bagel shop in Hazlet, New Jersey with two locations.

VOICE & TONE:
- Casual, confident, warm. Like talking to a regular who just walked in.
- Jersey attitude without being aggressive. Proud but not arrogant.
- Food-first. Make people TASTE and SMELL the bagels through words.
- Community-minded. This is a neighborhood shop, not a chain.
- Humor is welcome — dry, self-aware, food-obsessed.

BRAND FACTS (use naturally, don't force):
- Two locations: 694 Holmdel Rd and 1352 NJ-36 (Airport Plaza), both in Hazlet, NJ
- Open 6 AM - 3 PM, 7 days a week
- Fresh bagels boiled and baked every morning before dawn
- 15+ bagel varieties including gluten-free
- 13 cream cheese flavors
- Boar's Head deli meats
- Founded 2021, family-owned and operated
- Instagram: @bagelboyznj | Facebook: BagelBoyzNJ
- Website: bagelboyznj.com

NJ BAGEL TERMINOLOGY (use authentically):
- Taylor Ham (we're in Monmouth County — it's Taylor Ham here, though we respect both sides)
- SPK = Salt, Pepper, Ketchup (the holy trinity)
- "On an everything" = everything bagel
- "BEC" = Bacon Egg Cheese, "THEC" = Taylor Ham Egg Cheese
- The boil = what makes a real bagel (boiled before baked)

CONTENT ANGLES (rotate through these to keep content fresh):
1. Sensory Appeal — make them smell/taste/see the food
2. FOMO — limited, fresh, going fast
3. Ritual — the morning routine, Sunday tradition
4. Insider Knowledge — "if you know, you know"
5. Jersey Pride — NJ culture, local identity
6. Behind the Curtain — the craft, the process, the early mornings
7. Community — neighborhood love, family business
8. Humor — bagel puns, food obsession, self-aware fun
9. Seasonal — weather, holidays, time of year
10. Contrast — us vs chains, fresh vs frozen, real vs fake

FORMATTING RULES:
- NEVER use corporate language ("we're excited to announce", "don't miss out on this amazing opportunity")
- NO excessive exclamation marks. One max per post.
- Keep it punchy. Short sentences. Line breaks between thoughts.
- Hashtags: 5-10 for Instagram, 2-3 for Facebook
- ALWAYS end with a subtle CTA (visit us, order online, tag a friend, etc.)
- Platform-specific length: Instagram 100-150 words, Facebook 80-120 words`;

// ─── Content Angles ────────────────────────────────────────
const CONTENT_ANGLES = [
  'sensory_appeal', 'fomo', 'ritual', 'insider_knowledge',
  'jersey_pride', 'behind_curtain', 'community', 'humor',
  'seasonal', 'contrast'
];

// ─── History-Aware Deduplication ───────────────────────────
let recentPosts = [];

function pickCategory(runIndex) {
  // Rotate categories based on time of day and recent history
  const recentCategories = recentPosts.slice(0, 10).map(p => p.category);
  const available = CONTENT_CATEGORIES.filter(c => !recentCategories.includes(c.id));
  const pool = available.length > 0 ? available : CONTENT_CATEGORIES;

  // Morning bias toward breakfast items, midday toward catering/lunch, afternoon toward culture
  const biasMap = {
    0: ['daily_special', 'behind_scenes', 'customer_favorite'],
    1: ['daily_special', 'community', 'seasonal_trending'],
    2: ['nj_culture', 'seasonal_trending', 'community']
  };
  const biased = pool.filter(c => biasMap[runIndex]?.includes(c.id));
  const finalPool = biased.length > 0 ? biased : pool;

  return finalPool[Math.floor(Math.random() * finalPool.length)];
}

function pickAngle() {
  const recentAngles = recentPosts.slice(0, 7).map(p => p.angle);
  const available = CONTENT_ANGLES.filter(a => !recentAngles.includes(a));
  const pool = available.length > 0 ? available : CONTENT_ANGLES;
  return pool[Math.floor(Math.random() * pool.length)];
}

// ─── Scene Description for AI Image ───────────────────────
async function generateSceneDescription(category, angle) {
  const recentScenes = recentPosts.slice(0, 5).map(p => p.imageScene).filter(Boolean);
  const avoidText = recentScenes.length > 0
    ? `\n\nAVOID these recent scenes (create something different):\n${recentScenes.map((s, i) => `${i + 1}. ${s}`).join('\n')}`
    : '';

  const prompt = `Generate a single photographic scene description for an AI image generator.
The image is for a social media post by Bagel Boyz NJ, a bagel shop in Hazlet, New Jersey.

Category: ${category.name}
Angle: ${angle}

The scene MUST be:
- A photorealistic CLOSE-UP food photograph (overhead, 45-degree, or macro angle)
- Warm, inviting lighting (golden hour, morning light, or natural window light)
- Appetizing and mouth-watering — make people hungry
- Focused on the FOOD ITSELF — bagels, sandwiches, cream cheese, coffee, ingredients
- NEVER show shop interiors, storefronts, people, or staff — FOOD ONLY

Examples of great scenes:
- Close-up of a golden everything bagel fresh from the oven, steam rising, seeds glistening, on parchment paper
- Taylor Ham egg and cheese on an everything bagel, cross-section showing melted cheese and crispy meat
- Overhead shot of assorted bagels arranged on a wooden cutting board
- Hands pulling apart a warm bagel revealing soft chewy interior, steam visible
- Iced coffee in a clear cup next to a loaded breakfast sandwich, morning light
- Cream cheese being spread thickly on a toasted sesame bagel, knife mid-spread
- A fresh omelet being plated with golden home fries
${avoidText}

Return ONLY the scene description in 1-2 sentences. No labels, no preamble.`;

  return callAI('You are a food photography director creating scene descriptions.', prompt, { maxTokens: 200 });
}

// ─── Generate Post Content ─────────────────────────────────
async function generatePost(category, angle, platforms = ['instagram', 'facebook'], topic = null) {
  const historyContext = recentPosts.slice(0, 10).map(p =>
    `- [${p.category}/${p.angle}] Topic: "${p.topic}" — Hook: "${p.hookLine}"`
  ).join('\n');

  const recentTopicList = recentPosts.slice(0, 21).map(p => p.topic).filter(Boolean);
  const avoidTopics = recentTopicList.length > 0
    ? `\n\nDO NOT write about any of these recently covered topics:\n${recentTopicList.map((t, i) => `${i + 1}. ${t}`).join('\n')}`
    : '';

  const platformInstructions = platforms.map(p => {
    if (p === 'instagram') return 'INSTAGRAM: 100-150 words. Visual-first. 8-12 hashtags at the end. Casual, punchy.';
    if (p === 'facebook') return 'FACEBOOK: 80-120 words. Conversational. 2-3 hashtags max. Encourage comments/shares.';
    return '';
  }).filter(Boolean).join('\n');

  // Get current date context for seasonal relevance
  const now = new Date();
  const dateContext = `Current date: ${now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}. Season: ${getSeason(now)}.`;

  const prompt = `Generate a social media post for Bagel Boyz NJ.

${dateContext}

SPECIFIC TOPIC FOR THIS POST: ${topic || 'Choose something fresh and specific'}
CATEGORY: ${category.name}
CATEGORY DIRECTION: ${category.prompt}
CONTENT ANGLE: ${angle}

${platformInstructions}

${historyContext ? `RECENT POSTS (your post MUST be completely different from all of these):\n${historyContext}\n` : ''}${avoidTopics}

CRITICAL: Be SPECIFIC. Don't write generic "fresh bagels" posts. Focus tightly on the assigned topic. Every post must feel like it's about something NEW — a specific item, a specific moment, a specific story. No two posts should feel the same.

Return the post in this EXACT format (use these exact labels):
HOOK: [The opening line / hook — the first thing people read]
INSTAGRAM: [Full Instagram post text including hashtags]
FACEBOOK: [Full Facebook post text]
IMAGE_SCENE: [A 1-sentence description of the ideal food close-up photo for this post — MUST be a realistic food photograph, NOT a shop interior]`;

  const response = await callAI(BRAND_VOICE, prompt);

  // Parse labeled sections
  const sections = {};
  const lines = response.split('\n');
  let currentLabel = null;
  let currentContent = [];

  for (const line of lines) {
    const labelMatch = line.match(/^(HOOK|INSTAGRAM|FACEBOOK|IMAGE_SCENE):\s*(.*)/i);
    if (labelMatch) {
      if (currentLabel) sections[currentLabel] = currentContent.join('\n').trim();
      currentLabel = labelMatch[1].toUpperCase();
      currentContent = [labelMatch[2]];
    } else if (currentLabel) {
      currentContent.push(line);
    }
  }
  if (currentLabel) sections[currentLabel] = currentContent.join('\n').trim();

  return {
    hook: sections['HOOK'] || '',
    instagram: sections['INSTAGRAM'] || '',
    facebook: sections['FACEBOOK'] || '',
    imageScene: sections['IMAGE_SCENE'] || ''
  };
}

// ─── Season Helper ────────────────────────────────────────
function getSeason(date) {
  const month = date.getMonth();
  if (month >= 2 && month <= 4) return 'Spring';
  if (month >= 5 && month <= 7) return 'Summer';
  if (month >= 8 && month <= 10) return 'Fall';
  return 'Winter';
}

// ─── Main Orchestrator ─────────────────────────────────────
async function runSocialAutopilot(runIndex = 0) {
  ensureDir(UPLOAD_DIR);

  console.log(`[Autopilot] Run index: ${runIndex}`);

  // 1. Pick category, angle, and specific topic (all history-aware)
  const category = pickCategory(runIndex);
  const angle = pickAngle();
  const topic = pickTopic();
  console.log(`[Autopilot] Category: ${category.id} | Angle: ${angle} | Topic: ${topic}`);

  // 2. Generate post content with specific topic
  const post = await generatePost(category, angle, ['instagram', 'facebook'], topic);
  console.log(`[Autopilot] Hook: ${post.hook}`);

  // 3. Generate AI scene description (or use the one from post)
  const sceneDesc = post.imageScene || await generateSceneDescription(category, angle);
  console.log(`[Autopilot] Scene: ${sceneDesc}`);

  // 4. Generate branded images (landscape for Facebook, square for Instagram)
  const postId = uuidv4();
  let imagePaths = { landscape: null, square: null };

  try {
    imagePaths = await generateBrandedImages({
      sceneDescription: sceneDesc,
      headline: post.hook,
      outputDir: UPLOAD_DIR,
      postId
    });
    console.log(`[Autopilot] Images generated: ${JSON.stringify(imagePaths)}`);
  } catch (err) {
    console.error('[Autopilot] Image generation failed, posting text-only:', err.message);
  }

  // 5. Track post in history for deduplication
  recentPosts.unshift({
    category: category.id,
    angle,
    topic,
    hookLine: post.hook,
    imageScene: sceneDesc,
    timestamp: new Date().toISOString()
  });
  if (recentPosts.length > 21) recentPosts = recentPosts.slice(0, 21);

  // 6. Save post history to file (persists between runs)
  const historyFile = path.join(UPLOAD_DIR, 'post-history.json');
  try {
    fs.writeFileSync(historyFile, JSON.stringify(recentPosts, null, 2));
  } catch (e) {
    console.warn('[Autopilot] Could not save history file:', e.message);
  }

  // 7. Publish to platforms
  const results = { facebook: null, instagram: null };

  // Facebook (landscape image)
  try {
    results.facebook = await publishToFacebook({
      text: post.facebook,
      imagePath: imagePaths.landscape
    });
    console.log('[Autopilot] Facebook published:', results.facebook?.id || 'text-only');
  } catch (err) {
    console.error('[Autopilot] Facebook publish failed:', err.message);
  }

  // Instagram (square image)
  if (imagePaths.square) {
    try {
      results.instagram = await publishToInstagram({
        text: post.instagram,
        imagePath: imagePaths.square
      });
      console.log('[Autopilot] Instagram published:', results.instagram?.id || 'ok');
    } catch (err) {
      console.error('[Autopilot] Instagram publish failed:', err.message);
    }
  } else {
    console.log('[Autopilot] Skipping Instagram — no square image available');
  }

  // 8. Generate feed JSON for website
  try {
    await updateWebsiteFeed(post, imagePaths, category, angle);
  } catch (err) {
    console.warn('[Autopilot] Feed update failed:', err.message);
  }

  return { post, imagePaths, results };
}

// ─── Update Website Social Feed ────────────────────────────
async function updateWebsiteFeed(post, imagePaths, category, angle) {
  const feedFile = path.join(__dirname, '../../../social-feed.json');
  let feed = [];

  try {
    if (fs.existsSync(feedFile)) {
      feed = JSON.parse(fs.readFileSync(feedFile, 'utf-8'));
    }
  } catch (e) {
    feed = [];
  }

  // Add new post to front of feed
  feed.unshift({
    id: uuidv4(),
    text: post.instagram || post.facebook,
    hook: post.hook,
    category: category.id,
    angle,
    imageScene: post.imageScene,
    imagePath: imagePaths.square || imagePaths.landscape || null,
    platforms: ['instagram', 'facebook'],
    publishedAt: new Date().toISOString()
  });

  // Keep last 50 posts
  if (feed.length > 50) feed = feed.slice(0, 50);

  fs.writeFileSync(feedFile, JSON.stringify(feed, null, 2));
}

// ─── Load Post History on Startup ──────────────────────────
try {
  const historyFile = path.join(UPLOAD_DIR, 'post-history.json');
  if (fs.existsSync(historyFile)) {
    recentPosts = JSON.parse(fs.readFileSync(historyFile, 'utf-8'));
    console.log(`[ContentGen] Loaded ${recentPosts.length} recent posts from history`);
  }
} catch (e) {
  console.warn('[ContentGen] Could not load post history:', e.message);
}

module.exports = { runSocialAutopilot, generatePost, CONTENT_CATEGORIES, BRAND_VOICE };
