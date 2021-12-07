<?php 

	class Logger {
		
		private static function add($event, $data, $id = '') {
			$record = "[".date("Y-m-d H:i:s")."] [$id]".json_encode($data);
			$myfile = file_put_contents(__DIR__."/../logs/$event.log", $record.PHP_EOL, FILE_APPEND | LOCK_EX);
		}
				
		public static function debug($data, $id = '') {
			Logger::add('debug', $data, $id);
		}
		
		public static function info($data, $id = '') {
			Logger::add('info', $data, $id);
		}
		
		public static function notice($data, $id = '') {
			Logger::add('notice', $data, $id);
		}
		
		public static function warning($data, $id = '') {
			Logger::add('warning', $data, $id);
		}
		
		public static function error($data, $id = '') {
			Logger::add('error', $data, $id);
		}
		
		public static function critical($data, $id = '') {
			Logger::add('critical', $data, $id);
		}
		
		public static function alert($data, $id = '') {
			Logger::add('alert', $data, $id);
		}
		
		public static function emergency($data, $id = '') {
			Logger::add('emergency', $data, $id);
		}
		
	}