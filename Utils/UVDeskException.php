<?php

namespace Webkul\UVDesk\ApiBundle\Utils;

abstract class UVDeskException
{
    /**
     * [API-*] API Exception Codes
     */
    const API_UNAUTHORIZED = 'API-001';
    const API_NOT_AUTHENTICATED = 'API-002';
    const API_INSUFFICIENT_PARAMS = 'API-003';

    /**
     * [CC-*] Campus Connect Exception Codes
     */
    const USER_NOT_FOUND = 'CC-001';
    const INVALID_CREDNETIALS = 'CC-002';
    const UNEXPECTED_ERROR = 'CC-005';
}
