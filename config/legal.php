<?php

/*
| The Terms of Service and Privacy Policy (resources/views/legal/*). The website, the buyer app
| (GET /api/mobile/legal/{terms|privacy}) and the rider app all show these same two documents.
| Bump `version` whenever their wording changes: every account records the version it agreed to
| (terms_version / terms_accepted_at), which is the proof of consent if a dispute ever comes up.
*/
return [
    'version' => '2026-11-16',
    'effective_date' => '2026-11-16',
];
