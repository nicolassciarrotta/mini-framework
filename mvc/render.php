<?php

	class Render {

		private static $uniqueId = 0;

		private static function isAssoc(array $arr) {
			if (array() === $arr) {
				return false;
			}

			return array_keys($arr) !== range(0, count($arr) - 1);
		}

		public static function slugify($text) {
			$text = Normalizer::normalize($text, Normalizer::NFD);
			$text = strtolower($text);
			//$text = preg_replace('/[\\u0300-\\u036f]/', '', $text);
			//$text = preg_replace('/\\s+/', '-', $text);
			// $text = preg_replace('/[^\\w\\-]+/', '', $text);
			// $text = preg_replace('/\\-\\-+/', '-', $text);
			// $text = preg_replace('/^-+/', '', $text);
			// $text = preg_replace('/-+$/', '', $text);
			// $text = substr($text, 0, 75);
			$text = trim($text);
			$text = preg_replace('/[\s_-]+/', '', $text);

			return $text;
		}
		
		public static function interpolate($template, $data, $prefix = '', $history = []) {
			array_push($history, $data);
			$template = preg_replace('/\\{:id:\\}/', ++Render::$uniqueId, $template);

			foreach ($data as $name => $value) {
				if ( !is_array($value) ) {
					continue;
				}

				if ( Render::isAssoc($value) ) {
					continue;
				}

				$matches = [];
				$query = '/\\[' . $name . '\\]([\\s\\S]*?)\\[\\/' . $name . '\\]/i';
				preg_match_all($query, $template, $matches);
				
				$matches = $matches[1];
				$count = count($matches);
				
				foreach ($matches as $idx => $match) {					
					$content = "";
					foreach ($value as $staticIdx => $item) {
						$row = $match;
						
						if ( !in_array($value, $history, true) ) {
							$row = Render::interpolate($row, $item, $prefix, $history);
						}

						$content .= preg_replace('/\\{:idx:\\}/', $staticIdx, $row);
					}
					
					$template = preg_replace($query, $content, $template, 1);
				}
			}

			foreach ($data as $name => $value) {
				if ( is_array($value) ) {
					// Prevent circular references.
					if ( !in_array($value, $history, true) ) {
						$template = Render::interpolate($template, $value, $prefix . $name, $history);
					}

					continue;
				}

				$template = preg_replace('/\\{' . $prefix . $name . '\\}/i', $value, $template);
			}

			if ($prefix === '') {
				$template = preg_replace('/\\{[a-zA-Z0-9]+\\}/', '', $template);
			}

			return $template;
		}

		public static function view($name) {
			$view = __DIR__.'/../app/views/'.$name.'.html';
			
			if ( strpos($name, '.') !== false ) {
				throw new Exception("Posible path injection ($view).");
			}
			
			if ( !file_exists($view) ) {
				throw new Exception("La vista no existe ($view).");
			}
			
			$view = file_get_contents($view);
			
			$matches = [];
			$query = '/\\(include\\)([\\s\\S]*?)\\(\\/include\\)/i';
			preg_match_all($query, $view, $matches);
				
			$matches = $matches[1];
			$count = count($matches);
			
			foreach ($matches as $match) {
				$include = file_get_contents(__DIR__.'/../app/views/'.$match);				
				$view = preg_replace($query, $include, $view, 1);
			}
			
			return $view;
		}
	}