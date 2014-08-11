<?php

	class JXP_XML
	{
		public static function xmlToArray($xml)
		{
			$return = null;

			if (is_file($xml))
			{
				if (is_file($xml))
					$xml = file_get_contents($xml);
				else
					$return = 'no xml file found at: ' . $xml;
			}

			if (is_null($return))
			{
				libxml_use_internal_errors(true);

				$doc    = new DOMDocument();
				$return = !$doc->loadXML($xml) ? 'error parsing XML file' : self::_nodeToArray($doc->documentElement);
			}

			return $return;
		}

		private static function _nodeToArray($node)
		{
			$output = array();

			switch ($node->nodeType) {

				case XML_CDATA_SECTION_NODE:
				case XML_TEXT_NODE:

					$output = trim($node->textContent);

					break;

				case XML_ELEMENT_NODE:

					for ($i = 0, $m = $node->childNodes->length; $i < $m; $i++)
					{
						$child = $node->childNodes->item($i);
						$value = self::_nodeToArray($child);

						if (isset($child->tagName))
						{
							$tag = $child->tagName;

							if (!isset($output[$tag]))
							{
								$output[$tag] = array();
							}

							$output[$tag][] = $value;

						} else if ($value || $value === '0') {

							$output = $value;
						}
					}

					if ($node->attributes->length && !is_array($output))
					{
						$output = array('@content'=>$output);
					}

					if (is_array($output))
					{
						if ($node->attributes->length)
						{
							$attribute = array();

							foreach ($node->attributes as $attrName => $attrNode)
							{
								$attribute[$attrName] = $attrNode->value;
							}

							$output['@attr'] = $attribute;
						}

						foreach ($output as $tag => $value)
						{
							if (is_array($value) && count($value) === 1 && $tag != '@attr')
							{
								$output[$tag] = $value[0];
							}
						}
					}

					break;
			}

			return $output;
		}
	}