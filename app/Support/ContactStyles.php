<?php

namespace App\Support;

/** The message styles an admin can be given in the Contact panel: "Name: number (style)". */
class ContactStyles
{
    public const CERIA = 'ceria';

    public const FORMAL = 'formal';

    /** Old-fashioned English, thee and thy included: a joke style for one admin. */
    public const KUNO = 'kuno';

    public const ALL = [self::CERIA, self::FORMAL, self::KUNO];
}
