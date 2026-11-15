<?php

namespace App\Screenshot;

enum ImportMode: string
{
    /** A 32:9 image named like an existing final becomes its original, anything else a screenshot. */
    case Auto = 'auto';
    /** Every image is stored as an original, waiting for its final when none matches yet. */
    case Originals = 'originals';
    /** Every image becomes a screenshot, even a 32:9 one named like a final. */
    case Screenshots = 'screenshots';
}
