#!/usr/bin/env node
/**
 * Bagel Boyz NJ - Feed Generator Worker
 *
 * Runs every 2 hours to generate a static JSON file that powers
 * the social.html page on the website. Reads from the post history
 * and formats it for the frontend.
 */

require('dotenv').config({ path: require('path').join(__dirname, '../../.env') });

const fs = require('fs');
const path = require('path');

const FEED_OUTPUT = path.join(__dirname, '../../../social-feed.json');
const POST_HISTORY = path.join(__dirname, '../../uploads/social/post-history.json');

(async () => {
  console.log('[FeedGenerator] Generating website social feed...');

  try {
    let feed = [];

    // Read from social-feed.json (maintained by autopilot)
    if (fs.existsSync(FEED_OUTPUT)) {
      feed = JSON.parse(fs.readFileSync(FEED_OUTPUT, 'utf-8'));
    }

    // If no feed yet, create placeholder content
    if (feed.length === 0) {
      console.log('[FeedGenerator] No posts yet — feed will populate when autopilot runs');
    } else {
      console.log(`[FeedGenerator] Feed contains ${feed.length} posts`);
    }

    // Ensure feed file exists
    fs.writeFileSync(FEED_OUTPUT, JSON.stringify(feed, null, 2));
    console.log('[FeedGenerator] Feed file updated successfully');

  } catch (err) {
    console.error('[FeedGenerator] Error:', err.message);
    process.exitCode = 1;
  }

  process.exit(process.exitCode || 0);
})();
