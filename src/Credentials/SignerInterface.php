<?php
/**
 * Part of the Joomla Framework OAuth2 Package
 *
 * @copyright  Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\OAuth2\Credentials;

/**
 * OAuth message signer interface.
 *
 * @package     Joomla.Framework
 * @subpackage  OAuth2
 * @since       1.0
 */
interface SignerInterface
{
    /**
     * Calculate and return the OAuth message signature.
     *
     * @param   string  $baseString        The OAuth message as a normalized base string.
     * @param   string  $clientSecret      The OAuth client's secret.
     * @param   string  $credentialSecret  The OAuth credentials' secret.
     *
     * @return  string  The OAuth message signature.
     *
     * @since   1.0
     * @throws  \InvalidArgumentException
     */
    public function sign($baseString, $clientSecret, $credentialSecret);
}
