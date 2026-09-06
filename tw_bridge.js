const http = require("http");

const server = http.createServer(async (req, res) => {
  if (req.method === "POST" && req.url === "/redeem") {
    let body = "";
    req.on("data", chunk => body += chunk.toString());
    req.on("end", async () => {
      try {
        const parsed = JSON.parse(body);
        const hash = parsed.voucher_hash;
        const mobile = parsed.mobile;
        
        const response = await fetch(`https://gift.truemoney.com/campaign/vouchers/${hash}/redeem`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"
          },
          body: JSON.stringify({ mobile, voucher_hash: hash })
        });
        
        const data = await response.text();
        res.writeHead(response.status, { "Content-Type": "application/json" });
        res.end(data);
      } catch (err) {
        res.writeHead(500);
        res.end(JSON.stringify({ error: err.message }));
      }
    });
  } else {
    res.writeHead(404);
    res.end("Not Found");
  }
});

server.listen(3000, () => console.log("TW Bridge running on port 3000"));

