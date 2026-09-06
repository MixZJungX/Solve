const fs = require('fs');
const content = \import { createServer } from 'http';
import { gotScraping } from 'got-scraping';

const server = createServer(async (req, res) => {
  if (req.method === 'POST' && req.url === '/redeem') {
    let bodyStr = '';
    req.on('data', chunk => bodyStr += chunk.toString());
    req.on('end', async () => {
      try {
        const parsed = JSON.parse(bodyStr);
        const hash = parsed.voucher_hash;
        const mobile = parsed.mobile;
        
        const response = await gotScraping({
            url: 'https://gift.truemoney.com/campaign/vouchers/' + hash + '/redeem',
            method: 'POST',
            json: { mobile, voucher_hash: hash },
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            throwHttpErrors: false
        });
        
        res.writeHead(response.statusCode, { 'Content-Type': 'application/json' });
        res.end(response.body);
      } catch (err) {
        res.writeHead(500);
        res.end(JSON.stringify({ error: err.message }));
      }
    });
  } else {
    res.writeHead(404);
    res.end('Not Found');
  }
});

server.listen(3000, () => console.log('TW Bridge (got-scraping) running on port 3000'));
\;
fs.writeFileSync('tw_bridge.mjs', content, 'utf8');
