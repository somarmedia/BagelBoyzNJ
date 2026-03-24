/**
 * Bagel Boyz NJ - Brand Image Engine
 *
 * Generates branded social media images with AI-generated food photography backgrounds.
 * Cascading fallback: GPT-Image-1 -> OpenRouter Flux -> Solid gradient fallback.
 *
 * Adapted from BoatLife.ai brand-image-engine.js
 */

const { createCanvas, loadImage } = require('@napi-rs/canvas');
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

// Helper: Poll Kie.ai task until complete
async function pollKieTask(taskId, apiKey, maxWaitMs = 120000) {
  const start = Date.now();
  const pollInterval = 3000;

  while (Date.now() - start < maxWaitMs) {
    await new Promise(r => setTimeout(r, pollInterval));
    const res = await axios.get(`https://api.kie.ai/api/v1/jobs/recordInfo?taskId=${taskId}`, {
      headers: { 'Authorization': `Bearer ${apiKey}` },
      timeout: 15000
    });

    const state = res.data?.data?.state;
    const progress = res.data?.data?.progress || 0;
    console.log(`[ImageEngine] Kie.ai task ${state} (${progress}%)`);

    if (state === 'success') {
      const resultJson = JSON.parse(res.data.data.resultJson);
      return resultJson.resultUrls || [];
    }
    if (state === 'fail') {
      throw new Error(`Kie.ai failed: ${res.data.data.failMsg || 'unknown'}`);
    }
  }
  throw new Error('Kie.ai task timed out');
}

async function generateAIBackground(sceneDescription, size) {
  const sizeStr = `${size.width}x${size.height}`;
  console.log(`[ImageEngine] Generating ${sizeStr} background...`);
  const foodPrompt = `Professional food photography, photorealistic: ${sceneDescription}. Warm natural lighting, shallow depth of field, appetizing colors, close-up shot. No text, no logos, no watermarks, no people.`;

  // Try 1: Kie.ai Nano Banana Pro (Gemini-powered, best photorealism)
  if (process.env.KIE_API_KEY) {
    try {
      console.log('[ImageEngine] Trying Kie.ai Nano Banana Pro...');
      const aspectRatio = size.width > size.height ? '16:9' : (size.width === size.height ? '1:1' : '9:16');

      const res = await axios.post('https://api.kie.ai/api/v1/jobs/createTask', {
        model: 'nano-banana-pro',
        input: {
          prompt: foodPrompt,
          aspect_ratio: aspectRatio,
          resolution: '1K',
          output_format: 'png'
        }
      }, {
        headers: {
          'Authorization': `Bearer ${process.env.KIE_API_KEY}`,
          'Content-Type': 'application/json'
        },
        timeout: 30000
      });

      if (res.data?.data?.taskId) {
        const urls = await pollKieTask(res.data.data.taskId, process.env.KIE_API_KEY);
        if (urls.length > 0) {
          console.log('[ImageEngine] Kie.ai Nano Banana Pro success!');
          const imgRes = await axios.get(urls[0], { responseType: 'arraybuffer', timeout: 30000 });
          return loadImage(Buffer.from(imgRes.data));
        }
      }
    } catch (err) {
      console.warn('[ImageEngine] Kie.ai failed:', err.message);
    }
  }

  // Try 2: OpenRouter Flux (via chat completions with modalities)
  if (process.env.OPENROUTER_API_KEY) {
    try {
      console.log('[ImageEngine] Trying OpenRouter Flux...');
      const res = await axios.post('https://openrouter.ai/api/v1/chat/completions', {
        model: 'black-forest-labs/flux.2-pro',
        modalities: ['image'],
        messages: [{ role: 'user', content: foodPrompt }],
        image_config: {
          aspect_ratio: size.width > size.height ? '16:9' : '1:1'
        }
      }, {
        headers: {
          'Authorization': `Bearer ${process.env.OPENROUTER_API_KEY}`,
          'Content-Type': 'application/json'
        },
        timeout: 90000
      });

      const message = res.data?.choices?.[0]?.message;
      if (message?.images?.length > 0) {
        const dataUrl = message.images[0].image_url?.url || message.images[0].url;
        if (dataUrl) {
          const base64Data = dataUrl.includes(',') ? dataUrl.split(',')[1] : dataUrl;
          console.log('[ImageEngine] OpenRouter Flux success!');
          return loadImage(Buffer.from(base64Data, 'base64'));
        }
      }
    } catch (err) {
      console.warn('[ImageEngine] OpenRouter Flux failed:', err.message);
    }
  }

  // Try 3: OpenAI gpt-image-1
  if (process.env.OPENAI_API_KEY) {
    try {
      console.log('[ImageEngine] Trying GPT-Image-1...');
      const res = await axios.post('https://api.openai.com/v1/images/generations', {
        model: 'gpt-image-1',
        prompt: foodPrompt,
        n: 1,
        size: size.width > size.height ? '1536x1024' : '1024x1536',
        quality: 'high'
      }, {
        headers: { 'Authorization': `Bearer ${process.env.OPENAI_API_KEY}` },
        timeout: 60000
      });

      if (res.data?.data?.[0]?.b64_json) {
        return loadImage(Buffer.from(res.data.data[0].b64_json, 'base64'));
      }
      if (res.data?.data?.[0]?.url) {
        const imgRes = await axios.get(res.data.data[0].url, { responseType: 'arraybuffer', timeout: 30000 });
        return loadImage(Buffer.from(imgRes.data));
      }
    } catch (err) {
      console.warn('[ImageEngine] GPT-Image-1 failed:', err.message);
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

async function drawLogo(ctx, x, y, maxHeight = 80) {
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

// ─── Meme-Style Bold Text Helper ────────────────────────────
// White fill + thick black stroke = readable on any background
function drawMemeText(ctx, text, x, y, { align = 'center', strokeWidth = 5 } = {}) {
  ctx.textAlign = align;
  ctx.lineJoin = 'round';
  ctx.miterLimit = 2;

  // Black outline
  ctx.strokeStyle = '#000000';
  ctx.lineWidth = strokeWidth;
  ctx.strokeText(text, x, y);

  // White fill
  ctx.fillStyle = '#FFFFFF';
  ctx.fillText(text, x, y);
}

// ─── Layout 1: Centered Meme Text (Primary) ─────────────────
// Bold white text centered on image, meme-style with black outline
async function renderCenteredMemeLayout(ctx, w, h, headline) {
  // Subtle dark overlay so text pops
  drawOverlay(ctx, w, h, 0.3);

  if (headline) {
    const fontSize = w > 1000 ? 52 : 42;
    ctx.font = `900 ${fontSize}px "Impact", "Arial Black", sans-serif`;

    const lines = wrapText(ctx, headline.toUpperCase(), w - 120);
    const lineHeight = fontSize * 1.2;
    const totalHeight = lines.length * lineHeight;
    const startY = (h - totalHeight) / 2 + fontSize;

    for (let i = 0; i < Math.min(lines.length, 3); i++) {
      drawMemeText(ctx, lines[i], w / 2, startY + i * lineHeight, { strokeWidth: 6 });
    }
  }

  // Logo in bottom-right corner
  await drawLogo(ctx, w - 250, h - 100, 80);
}

// ─── Layout 2: Bottom Meme Text ──────────────────────────────
// Bold text anchored to bottom third
async function renderBottomMemeLayout(ctx, w, h, headline) {
  // Darker gradient at bottom for text readability
  const grad = ctx.createLinearGradient(0, h * 0.4, 0, h);
  grad.addColorStop(0, 'rgba(0,0,0,0)');
  grad.addColorStop(0.5, 'rgba(0,0,0,0.2)');
  grad.addColorStop(1, 'rgba(0,0,0,0.6)');
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, w, h);

  if (headline) {
    const fontSize = w > 1000 ? 48 : 38;
    ctx.font = `900 ${fontSize}px "Impact", "Arial Black", sans-serif`;

    const lines = wrapText(ctx, headline.toUpperCase(), w - 100);
    const lineHeight = fontSize * 1.2;
    const startY = h - 50 - (lines.length * lineHeight);

    for (let i = 0; i < Math.min(lines.length, 3); i++) {
      drawMemeText(ctx, lines[i], w / 2, startY + i * lineHeight, { strokeWidth: 5 });
    }
  }

  // Logo in bottom-right
  await drawLogo(ctx, w - 250, h - 100, 80);
}

// ─── Layout 3: Top Meme Text with Gold Accent ───────────────
async function renderTopMemeLayout(ctx, w, h, headline) {
  // Gold accent bar at top
  ctx.fillStyle = BRAND.colors.gold;
  ctx.fillRect(0, 0, w, 6);

  // Light overlay at top for readability
  const grad = ctx.createLinearGradient(0, 0, 0, h * 0.5);
  grad.addColorStop(0, 'rgba(0,0,0,0.5)');
  grad.addColorStop(0.6, 'rgba(0,0,0,0.1)');
  grad.addColorStop(1, 'rgba(0,0,0,0)');
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, w, h);

  if (headline) {
    const fontSize = w > 1000 ? 48 : 38;
    ctx.font = `900 ${fontSize}px "Impact", "Arial Black", sans-serif`;

    const lines = wrapText(ctx, headline.toUpperCase(), w - 120);
    const lineHeight = fontSize * 1.2;
    const startY = 30 + fontSize;

    for (let i = 0; i < Math.min(lines.length, 3); i++) {
      drawMemeText(ctx, lines[i], w / 2, startY + i * lineHeight, { strokeWidth: 5 });
    }
  }

  // Logo bottom-right
  await drawLogo(ctx, w - 250, h - 100, 80);
}

// ─── Main: Generate Branded Images ─────────────────────────
// Centered is weighted 50%, bottom 25%, top 25%
const LAYOUTS = [renderCenteredMemeLayout, renderCenteredMemeLayout, renderBottomMemeLayout, renderTopMemeLayout];

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
    const buffer = await canvas.encode('png');
    fs.writeFileSync(filepath, buffer);
    results[key] = filepath;

    console.log(`[ImageEngine] Saved ${key}: ${filepath}`);
  }

  return results;
}

module.exports = { generateBrandedImages, generateAIBackground, BRAND, SIZES };
