<?php

declare(strict_types=1);

// Expected serialised data models (tab-separated) for the models built in the serializer test.
return [
    'payment order' => "qr-0001\t1\t1\t125.5\tEUR\t20261015\t20260042\t0308\t\t\tFaktura 2026-0042\t1\tSK9611000000002918599669\tTATRSKBX\t0\t0\tJana Novakova\tHlavna 1\tKosice",
    'standing order' => "qr-0002\t1\t2\t30\tEUR\t\t\t\t\t\t\t1\tSK9611000000002918599669\t\t1\t15\t\tm\t20271231\t0\tUtulok Labka\t\t",
    'direct debit' => "qr-0003\t1\t4\t12.9\tEUR\t\t\t\t\t\t\t1\tSK9611000000002918599669\t\t0\t1\t\t\t20260099\t\t\t\t\t\t\t\tVetClinic s.r.o.\t\t",
];
