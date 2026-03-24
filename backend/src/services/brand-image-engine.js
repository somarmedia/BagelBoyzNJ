/**
 * Bagel Boyz NJ - Brand Image Engine
 *
 * Generates branded social media images with AI-generated food photography backgrounds.
 * Cascading fallback: GPT-Image-1 -> OpenRouter Flux -> Solid gradient fallback.
 *
 * Adapted from BoatLife.ai brand-image-engine.js
 */

const { createCanvas, loadImage, registerFont } = require('canvas');
const axios = require('axios');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

// ─── Brand Constants ───────────────────────────────────────
const BRAND = {
  colors: {
    gold: '#D4901E',
    goldLight: '#E8A83C',
    goldDark: '#B5780F',
    brown: '#3E2214',
    brownLight: '#5C3A26',
    cream: '#FFF8F0',
    white: '#FFFFFF',
    black: '#1A1A1A'
  },
  fonts: {
    heading: 'bold 48px "Inter", sans-serif',
    subheading: 'bold 28px "Inter", sans-serif',
    body: '22px "Inter", sans-serif',
    small: '16px "Inter", sans-serif'
  }
};

const SIZES = {
  landscape: { width: 1200, height: 628 },  // Facebook, Twitter
  square: { width: 1080, height: 1080 }      // Instagram
};

const LOGO_PATH = path.join(__dirname, '../../../img/BBLOGO.1000px.png');
const LOGO_BLACK_PATH = path.join(__dirname, '../../../img/BBLOGO.1000px-Black.png');

// ─── AI Background Generation (Cascading Fallback) ────────
async function generateAIBackground(sceneDescription, size) {
  const sizeStr = `${size.width}x${size.height}`;
  console.log(`[ImageEngine] Generating ${sizeStr} background...`);

  // Try 1: OpenAI gpt-image-1
  if (process.env.OPENAI_API_KEY) {
    try {
      console.log('[ImageEngine] Trying GPT-Image-1...');
      const res = await axios.post('https://api.openai.com/v1/images/generations', {
        model: 'gpt-image-1',
        prompt: `Professional food photography: ${sceneDescription}. Warm natural lighting, shallow depth of field, appetizing colors. No text, no logos, no watermarks.`,
        n: 1,
        size: size.width > size.height ? '1536x1024' : '1024x1536',
        quality: 'high'
      }, {
        headers: { 'Authorization': `Bearer ${process.env.OPENAI_API_KEY}` },
        timeout: 60000
      });

      if (res.data?.data?.[0]?.b64_json) {
        const buf = Buffer.from(res.data.data[0].b64_json, 'base64');
        return loadImage(buf);
      }
      if (res.data?.data?.[0]?.url) {
        const imgRes = await axios.get(res.data.data[0].url, { responseType: 'arraybuffer', timeout: 30000 });
        return loadImage(Buffer.from(imgRes.data));
      }
    } catch (err) {
      console.warn('[ImageEngine] GPT-Image-1 failed:', err.message);
    }
  }

  // Try 2: OpenRouter Flux
  if (process.env.OPENROUTER_API_KEY) {
    try {
      console.log('[ImageEngine] Trying OpenRouter Flux...');
      const res = await axios.post('https://openrouter.ai/api/v1/images/generations', {
        model: 'black-forest-labs/flux-1.1-pro',
        prompt: `Professional food photography: ${sceneDescription}. Warm natural lighting, appetizing.`,
        n: 1,
        size: size.width >= size.height ? '1024x768' : '768x1024'
      }, {
        headers: { 'Authorization': `Bearer ${process.env.OPENROUTER_API_KEY}` },
        timeout: 60000
      });

      if (res.data?.data?.[0]?.url) {
        const imgRes = await axios.get(res.data.data[0].url, { responseType: 'arraybuffer', timeout: 30000 });
        return loadImage(Buffer.from(imgRes.data));
      }
    } catch (err) {
      console.warn('[ImageEngine] OpenRouter Flux failed:', err.message);
    }
  }

  // Fallback: Warm gradient
  console.log('[ImageEngine] Using warm gradient fallback');
  return null;
}

// ─── Canvas Helpers ────────────────────────────────────────
function drawGradientBackground(ctx, w, h) {
  const grad = ctx.createLinearGradient(0, 0, w, h);
  grad.addColorStop(0, BRAND.colors.brown);
  grad.addColorStop(0.6, '#2C1810');
  grad.addColorStop(1, BRAND.colors.goldDark);
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, w, h);
}

function drawBgImage(ctx, img, w, h) {
  const scale = Math.max(w / img.width, h / img.height);
  const sw = img.width * scale;
  const sh = img.height * scale;
  ctx.drawImage(img, (w - sw) / 2, (h - sh) / 2, sw, sh);
}

function drawOverlay(ctx, w, h, opacity = 0.35) {
  const grad = ctx.createLinearGradient(0, h * 0.4, 0, h);
  grad.addColorStop(0, `rgba(0,0,0,0)`);
  grad.addColorStop(1, `rgba(0,0,0,${opacity})`);
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, w, h);
}

function wrapText(ctx, text, maxWidth) {
  const words = text.split(' ');
  const lines = [];
  let currentLine = '';

  for (const word of words) {
    const testLine = currentLine ? `${currentLine} ${word}` : word;
    if (ctx.measureText(testLine).width > maxWidth && currentLine) {
      lines.push(currentLine);
      currentLine = word;
    } else {
      currentLine = testLine;
    }
  }
  if (currentLine) lines.push(currentLine);
  return lines;
}

async function drawLogo(ctx, x, y, maxHeight = 40) {
  try {
    const logo = await loadImage(LOGO_PATH);
    const scale = maxHeight / logo.height;
    const w = logo.width * scale;
    ctx.drawImage(logo, x, y, w, maxHeight);
    return w;
  } catch (err) {
    console.warn('[ImageEngine] Could not load logo:', err.message);
    return 0;
  }
}

// ─── Layout: Bottom Text Bar ───────────────────────────────
async function renderBottomTextLayout(ctx, w, h, headline) {
  // Dark gradient overlay at bottom
  const grad = ctx.createLinearGradient(0, h * 0.5, 0, h);
  grad.addColorStop(0, 'rgba(0,0,0,0)');
  grad.addColorStop(0.5, 'rgba(0,0,0,0.3)');
  grad.addColorStop(1, 'rgba(0,0,0,0.75)');
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, w, h);

  // Headline text
  if (headline) {
    const fontSize = w > 1000 ? 36 : 28;
    ctx.font = `bold ${fontSize}px "Inter", sans-serif`;
    ctx.fillStyle = BRAND.colors.white;
    ctx.textAlign = 'left';

    const lines = wrapText(ctx, headline, w - 80);
    const lineHeight = fontSize * 1.3;
    const startY = h - 30 - (lines.length * lineHeight);

    for (let i = 0; i < Math.min(lines.length, 3); i++) {
      ctx.fillText(lines[i], 40, startY + i * lineHeight);
    }
  }

  // Logo in bottom-right
  await drawLogo(ctx, w - 180, h - 55, 35);
}

// ─── Layout: Top Bar ───────────────────────────────────────
async function renderTopBarLayout(ctx, w, h, headline) {
  // Gold bar at top
  ctx.fillStyle = BRAND.colors.gold;
  ctx.fillRect(0, 0, w, 70);

  // Logo in top bar
  await drawLogo(ctx, 20, 15, 40);

  // Subtle bottom overlay for text
  if (headline) {
    drawOverlay(ctx, w, h, 0.4);
    const fontSize = w > 1000 ? 34 : 26;
    ctx.font = `bold ${fontSize}px "Inter", sans-serif`;
    ctx.fillStyle = BRAND.colors.white;
    ctx.textAlign = 'center';

    const lines = wrapText(ctx, headline, w - 100);
    const lineHeight = fontSize * 1.3;
    const startY = h - 60 - (lines.length * lineHeight);

    for (let i = 0; i < Math.min(lines.length, 3); i++) {
      ctx.fillText(lines[i], w / 2, startY + i * lineHeight);
    }
  }
}

// ─── Layout: Minimal Logo ──────────────────────────────────
async function renderMinimalLayout(ctx, w, h) {
  drawOverlay(ctx, w, h, 0.15);
  await drawLogo(ctx, 30, h - 55, 35);
}

// ─── Main: Generate Branded Images ─────────────────────────
const LAYOUTS = [renderBottomTextLayout, renderTopBarLayout, renderMinimalLayout];

async function generateBrandedImages({ sceneDescription, headline, outputDir, postId }) {
  const results = { landscape: null, square: null };

  for (const [key, size] of Object.entries(SIZES)) {
    const { width, height } = size;
    const canvas = createCanvas(width, height);
    const ctx = canvas.getContext('2d');

    // 1. Draw background (AI or gradient fallback)
    const bgImage = await generateAIBackground(sceneDescription, size);
    if (bgImage) {
      drawBgImage(ctx, bgImage, width, height);
    } else {
      drawGradientBackground(ctx, width, height);
    }

    // 2. Apply layout
    const layoutIdx = crypto.createHash('md5').update(postId + key).digest()[0] % LAYOUTS.length;
    await LAYOUTS[layoutIdx](ctx, width, height, headline);

    // 3. Save to file
    const filename = `${postId}-${key}.png`;
    const filepath = path.join(outputDir, filename);
    const buffer = canvas.toBuffer('image/png');
    fs.writeFileSync(filepath, buffer);
    results[key] = filepath;

    console.log(`[ImageEngine] Saved ${key}: ${filepath}`);
  }

  return results;
}

module.exports = { generateBrandedImages, generateAIBackground, BRAND, SIZES };
