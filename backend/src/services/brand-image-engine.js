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

  // Try 2: OpenRouter Image Generation (Flux or other available model)
  if (process.env.OPENROUTER_API_KEY) {
    try {
      console.log('[ImageEngine] Trying OpenRouter image generation...');
      const res = await axios.post('https://openrouter.ai/api/v1/images/generations', {
        model: 'black-forest-labs/flux-1.1-pro',
        prompt: `Professional food photography, photorealistic: ${sceneDescription}. Warm natural lighting, shallow depth of field, appetizing colors, close-up shot. No text, no logos, no watermarks, no people.`,
        n: 1,
        size: size.width >= size.height ? '1024x768' : '1024x1024'
      }, {
        headers: {
          'Authorization': `Bearer ${process.env.OPENROUTER_API_KEY}`,
          'Content-Type': 'application/json'
        },
        timeout: 90000
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
      console.warn('[ImageEngine] OpenRouter image gen failed:', err.message);
      // Try alternate model
      try {
        console.log('[ImageEngine] Trying OpenRouter alternate model...');
        const res = await axios.post('https://openrouter.ai/api/v1/images/generations', {
          model: 'stabilityai/stable-diffusion-xl',
          prompt: `Professional food photography, photorealistic: ${sceneDescription}. Warm natural lighting, shallow depth of field, appetizing colors, close-up shot. No text, no logos, no watermarks, no people.`,
          n: 1,
          size: '1024x1024'
        }, {
          headers: {
            'Authorization': `Bearer ${process.env.OPENROUTER_API_KEY}`,
            'Content-Type': 'application/json'
          },
          timeout: 90000
        });

        if (res.data?.data?.[0]?.b64_json) {
          return loadImage(Buffer.from(res.data.data[0].b64_json, 'base64'));
        }
        if (res.data?.data?.[0]?.url) {
          const imgRes = await axios.get(res.data.data[0].url, { responseType: 'arraybuffer', timeout: 30000 });
          return loadImage(Buffer.from(imgRes.data));
        }
      } catch (err2) {
        console.warn('[ImageEngine] OpenRouter alternate also failed:', err2.message);
      }
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

// ─── Layout 1: Black Banner Top (Primary - matches brand style) ──
// Black semi-transparent banner at top-center with bold white text + logo at bottom
async function renderBlackBannerTopLayout(ctx, w, h, headline) {
  if (headline) {
    const fontSize = w > 1000 ? 44 : 36;
    ctx.font = `bold ${fontSize}px "Inter", sans-serif`;
    ctx.textAlign = 'center';

    const lines = wrapText(ctx, headline, w - 120);
    const lineHeight = fontSize * 1.25;
    const bannerPadding = 20;
    const bannerHeight = (lines.length * lineHeight) + (bannerPadding * 2) + 10;
    const bannerY = Math.round(h * 0.08);

    // Black banner background with rounded corners
    const bannerX = Math.round(w * 0.08);
    const bannerW = Math.round(w * 0.84);
    const radius = 12;
    ctx.fillStyle = 'rgba(0,0,0,0.82)';
    ctx.beginPath();
    ctx.moveTo(bannerX + radius, bannerY);
    ctx.lineTo(bannerX + bannerW - radius, bannerY);
    ctx.arcTo(bannerX + bannerW, bannerY, bannerX + bannerW, bannerY + radius, radius);
    ctx.lineTo(bannerX + bannerW, bannerY + bannerHeight - radius);
    ctx.arcTo(bannerX + bannerW, bannerY + bannerHeight, bannerX + bannerW - radius, bannerY + bannerHeight, radius);
    ctx.lineTo(bannerX + radius, bannerY + bannerHeight);
    ctx.arcTo(bannerX, bannerY + bannerHeight, bannerX, bannerY + bannerHeight - radius, radius);
    ctx.lineTo(bannerX, bannerY + radius);
    ctx.arcTo(bannerX, bannerY, bannerX + radius, bannerY, radius);
    ctx.closePath();
    ctx.fill();

    // White text centered in banner
    ctx.fillStyle = BRAND.colors.white;
    const textStartY = bannerY + bannerPadding + fontSize;
    for (let i = 0; i < Math.min(lines.length, 3); i++) {
      ctx.fillText(lines[i], w / 2, textStartY + i * lineHeight);
    }
  }

  // Logo in bottom-right corner
  await drawLogo(ctx, w - 170, h - 60, 40);
}

// ─── Layout 2: Bottom Text Bar ─────────────────────────────
async function renderBottomTextLayout(ctx, w, h, headline) {
  // Dark gradient overlay at bottom
  const grad = ctx.createLinearGradient(0, h * 0.45, 0, h);
  grad.addColorStop(0, 'rgba(0,0,0,0)');
  grad.addColorStop(0.4, 'rgba(0,0,0,0.3)');
  grad.addColorStop(1, 'rgba(0,0,0,0.8)');
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, w, h);

  if (headline) {
    const fontSize = w > 1000 ? 40 : 32;
    ctx.font = `bold ${fontSize}px "Inter", sans-serif`;
    ctx.fillStyle = BRAND.colors.white;
    ctx.textAlign = 'left';

    const lines = wrapText(ctx, headline, w - 100);
    const lineHeight = fontSize * 1.3;
    const startY = h - 35 - (lines.length * lineHeight);

    for (let i = 0; i < Math.min(lines.length, 3); i++) {
      ctx.fillText(lines[i], 45, startY + i * lineHeight);
    }
  }

  // Logo in bottom-right
  await drawLogo(ctx, w - 170, h - 60, 40);
}

// ─── Layout 3: Gold Accent Bar ─────────────────────────────
async function renderGoldAccentLayout(ctx, w, h, headline) {
  // Thin gold accent bar at top
  ctx.fillStyle = BRAND.colors.gold;
  ctx.fillRect(0, 0, w, 6);

  // Black banner at bottom for text
  if (headline) {
    const fontSize = w > 1000 ? 38 : 30;
    ctx.font = `bold ${fontSize}px "Inter", sans-serif`;
    ctx.textAlign = 'center';

    const lines = wrapText(ctx, headline, w - 100);
    const lineHeight = fontSize * 1.25;
    const bannerHeight = (lines.length * lineHeight) + 50;
    const bannerY = h - bannerHeight;

    // Black bar at bottom
    ctx.fillStyle = 'rgba(0,0,0,0.85)';
    ctx.fillRect(0, bannerY, w, bannerHeight);

    // Gold accent line above text
    ctx.fillStyle = BRAND.colors.gold;
    ctx.fillRect(0, bannerY, w, 3);

    // White text
    ctx.fillStyle = BRAND.colors.white;
    const textStartY = bannerY + 30 + fontSize * 0.3;
    for (let i = 0; i < Math.min(lines.length, 3); i++) {
      ctx.fillText(lines[i], w / 2, textStartY + i * lineHeight);
    }
  }

  // Logo bottom-left
  await drawLogo(ctx, 30, h - 55, 35);
}

// ─── Main: Generate Branded Images ─────────────────────────
// Black banner top is weighted 50%, the others 25% each for variety
const LAYOUTS = [renderBlackBannerTopLayout, renderBlackBannerTopLayout, renderBottomTextLayout, renderGoldAccentLayout];

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
