<?php

/*--------------------------------------------------

	Can be called statically or dynamically:

		$rstA = db::query('
			SELECT
			FROM');

		$rstA = $db->query('
			SELECT
			FROM');

		$rstA = $this->db->query('
			SELECT
			FROM');

	Useful for the form object which may need to
	known which $link to use:

		$form = new form(
				'database_table' => array(DB_T_PREFIX . 'example_table', 'a', $db), // Alias and database connection
			);

		if ($db === NULL) {
			$db = db; // ???
		}

	How about a generic

		$rstA = db_query('SELECT
							FROM');

--------------------------------------------------*/


//--------------------------------------------------
//
// $rs = db::query('SELECT
// 					FROM');
//
// $rst = db:q('SELECT
// 			FROM');
//
// $rst = $myDb:q('SELECT
// 				FROM');
//
// $rst = $this->db:q('SELECT
// 					FROM');
//
// In this example, we could return a "db_result" object,
// which could be used in the form of:
//
// if ($rst->num_rows() > 0) {
// }
//
// while ($rst->next()) {
// 	$user[]['address'] = $rst->fetch_assoc();
// 	$user[]['address'] = $rst->get('address');
// 	$user[]['address'] = $rst->getAddress();
// 	$user[]['address'] = $rst->address;
// }
//
// This may be helpful when searching for all
// references to address... but remember tab
// completion.
//
//--------------------------------------------------
//
//	db('SELECT
//			*
//		FROM
//			' . DB_T_PREFIX . 'table_name
//		WHERE
//			field = val';
//
//	db::ex('SELECT
//				*
//			FROM
//				' . DB_T_PREFIX . 'table_name
//			WHERE
//				field = val';
//
//	$db->query('SELECT
//					*
//				FROM
//					' . DB_T_PREFIX . 'table_name
//				WHERE
//					field = val');
//
//--------------------------------------------------
//
// $rs = DB::query('SELECT
// 					FROM');
//	db::ex('SELECT
//			FROM');
//	$db->query('SELECT
//				FROM');
//	$this->query('SELECT
//				FROM');
//
//--------------------------------------------------

?>