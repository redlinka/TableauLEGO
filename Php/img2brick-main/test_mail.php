<?php
// Disabled public test endpoint to avoid credential leakage and abuse.
http_response_code(403);
echo 'Disabled test endpoint.';
exit;
