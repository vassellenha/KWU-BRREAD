<?php

namespace App\Support;

/** The message styles an admin can be given in the Contact panel: "Name: number (style)". */
class ContactStyles
{
    public const CERIA = 'ceria';

    public const FORMAL = 'formal';

    public const ALL = [self::CERIA, self::FORMAL];
}
