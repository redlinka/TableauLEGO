<?php
// Disabled public test endpoint to avoid leaking database details.
http_response_code(403);
echo 'Disabled test endpoint.';
exit;
