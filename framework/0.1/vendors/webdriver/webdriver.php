<?php
// Copyright 2004-present Facebook. All Rights Reserved.
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

require_once('webdriver_base.php');
require_once('webdriver_main.php');
require_once('webdriver_container.php');
require_once('webdriver_session.php');
require_once('webdriver_element.php');
require_once('webdriver_environment.php');
require_once('webdriver_exceptions.php');
require_once('webdriver_simple_item.php');

function split_keys($toSend){
	$payload = array("value" => preg_split("//u", $toSend, -1, PREG_SPLIT_NO_EMPTY)); // str_split not UTF-8 friendly
	return $payload;
}