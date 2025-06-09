<?php
// TOKENS FOR TG BOTS (GLOBAL and LOCAL)
define('BOT_TOKEN', '7993358429:AAH3EfKtSW7oqyN1fVWBAQsD6ehKZViF1do');
define('LOCAL_BOT_TOKEN', '8111791435:AAHs41kdMZ0PBkm2lt0lNavG9vI9xCiJ_FA');
// TOKENS FOR USERS AUTH
define('SECRET_KEY', 'S+a2pTxd4NTyzC6HrmYUy6AEMIaq+jYfpqfJ5FqqM20=');
define('TOKEN_EXPIRE', 3600 * 24 * 3); // 3 days

function use_pack($server_type)
{
    if ($server_type == "PRODUCTION") {
        return ['localhost', 'dustore', 'leo', ''];
    } else if ($server_type == "LOCAL") {
        return ['localhost', 'dustore', 'root', ''];
    }
}
