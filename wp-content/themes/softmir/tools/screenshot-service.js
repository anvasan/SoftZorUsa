const puppeteer = require('puppeteer');
const fs = require('fs');

(async () => {
    const url = process.argv[2];
    const filepath = process.argv[3];

    if (!url || !filepath) {
        console.error('Usage: node screenshot-service.js <url> <filepath>');
        process.exit(1);
    }

    try {
        const browser = await puppeteer.launch({
            headless: "new",
            ignoreHTTPSErrors: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--window-size=1920,1080',
                '--disable-web-security',
                '--disable-features=IsolateOrigins,site-per-process'
            ]
        });

        const page = await browser.newPage();
        await page.setViewport({ width: 1920, height: 1080 });

        console.log(`Navigating to ${url}...`);

        // Go to URL and wait until network is mostly idle
        // Some sites are very slow or have infinite loaders, so 30 seconds max
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });

        // Wait a small extra time just in case of slow JS rendering
        await new Promise(resolve => setTimeout(resolve, 3000));

        console.log(`Taking screenshot...`);
        // We will output JPEG for better file size performance on WP
        await page.screenshot({
            path: filepath,
            type: filepath.endsWith('.webp') ? 'webp' : 'jpeg',
            quality: 80,
            fullPage: false // only viewport 
        });

        await browser.close();
        console.log(`Screenshot saved to ${filepath}`);
        process.exit(0);
    } catch (error) {
        console.error('Screenshot failed:', error);
        process.exit(1);
    }
})();
