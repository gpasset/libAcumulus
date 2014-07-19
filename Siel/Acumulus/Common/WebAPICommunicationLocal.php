<?php
/**
 * @file Definition of Siel\Acumulus\WebAPICommunicationLocal.
 */

namespace Siel\Acumulus\Common;

use Siel\Acumulus\Common\WebAPICommunication;

/**
 * WebAPICommunicationLocal is a class derived from WebAPICommunication that can
 * be used for testing purposes. It does not actually send the message to
 * Acumulus and fakes a response.
 *
 * @package Siel\Acumulus
 */
class WebAPICommunicationLocal extends WebAPICommunication {
  /**
   * @inheritdoc
   */
  protected function sendHttpPost($uri, $post) {
    if ($this->config->getOutputFormat() === 'json') {
      $response = str_replace(array("\r", "\n", "\t"), '', '{
				"errors": {
						"count_errors": "0"
				},
				"warnings": {
					"warning": [ {
						"code": "599",
						"codetag": "LOCAL",
						"message": "Warning - The message has not been sent. The communication layer operates in local debug mode."
					} ],
					"count_warnings": "1"
				},
				"status": "0"
			}');
    }
    else {
      $response = '<myxml>
        <errors>
          <count_errors>0</count_errors>
        </errors>
        <warnings>
          <warning>
            <code>599</code>
            <codetag>LOCAL</codetag>
            <message>Warning - The message has not been sent. The communication layer operates in local debug mode.</message>
          </warning>
        </warnings>
      </myxml>';
    }
    return $response;
  }
}
