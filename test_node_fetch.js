const url = 'https://gift.truemoney.com/campaign/vouchers/01a070fb7f8e7baa94d52531892d4f/redeem';
fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ mobile: '0812345678', voucher_hash: '01a070fb7f8e7baa94d52531892d4f' })
}).then(res => res.text()).then(txt => console.log('Response:', txt.substring(0, 500))).catch(console.error);
