<?php
\ = "https://gift.truemoney.com/campaign/?v=01a070fb7f8e7baa94d52531892d4f";
\ = "";
if (preg_match('/v=([a-zA-Z0-9]+)/', \, \)) {
    \ = \[1];
} else {
    \ = preg_replace('/[^a-zA-Z0-9]/', '', \);
}
echo "Hash: " . \;
