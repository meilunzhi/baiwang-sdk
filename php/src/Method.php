<?php

namespace Melon\Baiwang;

final class Method
{
    public const AUTH = 'baiwang.oauth.token';
    public const INVOICING = 'baiwang.output.invoice.issue';
    public const INVOICE_QUERY = 'baiwang.output.invoice.query';
    public const LAYOUT_QUERY = 'baiwang.output.format.query';
    public const CLOUD_HEAD_UP = 'baiwang.bizinfo.companySearch';
    public const PRE_INVOICE = 'baiwang.output.preinvoice.issue';
    public const VOID_INVOICED = 'baiwang.output.invoice.cancel';
    public const RED_LETTER_ISSUANCE = 'baiwang.output.redinvoice.issued';

    private function __construct()
    {
    }
}
