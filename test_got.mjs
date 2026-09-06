import { gotScraping } from 'got-scraping';

const url = 'https://gift.truemoney.com/campaign/vouchers/01a070fb7f8e7baa94d52531892d4f/redeem';

(async () => {
    try {
        const { body } = await gotScraping({
            url: url,
            method: 'POST',
            json: { mobile: '0812345678', voucher_hash: '01a070fb7f8e7baa94d52531892d4f' },
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        console.log('Response:', body.substring(0, 500));
    } catch (e) {
        if (e.response) {
            console.log('Error Response:', e.response.body.substring(0, 500));
        } else {
            console.log('Error:', e.message);
        }
    }
})();
