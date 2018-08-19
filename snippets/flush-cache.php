<?php

\Bnomei\AutoID::flush();
\Bnomei\Modified::flush();
\Bnomei\AutoID::rebuildIndex(true);  // force
