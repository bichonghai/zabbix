<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

class testPageAdministrationMediaTypes extends CLegacyWebTest {

	private $sqlHashMediaType = '';
	private $oldHashMediaType = '';

	private $mediatypes = [
		MEDIA_TYPE_EMAIL => 'Email',
		MEDIA_TYPE_EXEC => 'Script',
		MEDIA_TYPE_SMS => 'SMS',
		MEDIA_TYPE_JABBER => 'Jabber',
		MEDIA_TYPE_EZ_TEXTING => 'Ez Texting'
	];

	private function calculateHash($mediatypeid) {
		$this->sqlHashMediaType = 'SELECT * FROM media_type WHERE mediatypeid='.$mediatypeid;
		$this->oldHashMediaType = CDBHelper::getHash($this->sqlHashMediaType);
	}

	private function verifyHash() {
		$this->assertEquals($this->oldHashMediaType, CDBHelper::getHash($this->sqlHashMediaType));
	}

	public static function allMediaTypes() {
		return CDBHelper::getDataProvider('SELECT mediatypeid,description FROM media_type');
	}

	public function testPageAdministrationMediaTypes_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=mediatype.list');
		$this->zbxTestCheckTitle('Configuration of media types');

		$this->zbxTestCheckHeader('Media types');
		$this->zbxTestTextPresent('Displaying');
		$this->zbxTestTextPresent(['Name', 'Type', 'Status', 'Used in actions', 'Details']);

		$dbResult = DBselect('SELECT description,type FROM media_type');

		while ($dbRow = DBfetch($dbResult)) {
			$this->zbxTestTextPresent([$dbRow['description'], $this->mediatypes[$dbRow['type']]]);
		}

		$this->zbxTestTextPresent(['Enable', 'Disable', 'Delete']);
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_Disable($mediatype) {
		DBexecute(
			'UPDATE media_type'.
			' SET status='.MEDIA_TYPE_STATUS_ACTIVE.
			' WHERE mediatypeid='.$mediatype['mediatypeid']
		);

		$this->zbxTestLogin('zabbix.php?action=mediatype.list');
		$this->zbxTestCheckboxSelect('mediatypeids_'.$mediatype['mediatypeid']);
		$this->zbxTestClickButton('mediatype.disable');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Media type disabled');

		$this->assertEquals(1, CDBHelper::getCount(
			'SELECT NULL'.
			' FROM media_type'.
			' WHERE status='.MEDIA_TYPE_STATUS_DISABLED.
				' AND mediatypeid='.$mediatype['mediatypeid']
		));
	}

	/**
	 * @dataProvider allMediaTypes
	 */
	public function testPageAdministrationMediaTypes_Enable($mediatype) {
		DBexecute(
			'UPDATE media_type'.
			' SET status='.MEDIA_TYPE_STATUS_DISABLED.
			' WHERE mediatypeid='.$mediatype['mediatypeid']
		);

		$this->zbxTestLogin('zabbix.php?action=mediatype.list');
		$this->zbxTestCheckboxSelect('mediatypeids_'.$mediatype['mediatypeid']);
		$this->zbxTestClickButton('mediatype.enable');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of media types');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Media type enabled');

		$this->assertEquals(1, CDBHelper::getCount(
			'SELECT NULL'.
			' FROM media_type'.
			' WHERE status='.MEDIA_TYPE_STATUS_ACTIVE.
				' AND mediatypeid='.$mediatype['mediatypeid']
		));
	}

	/**
	 * @dataProvider allMediaTypes
	 * @backup-once media_type
	 */
	public function testPageAdministrationMediaTypes_Delete($mediatype) {
		$dbRow = DBfetch(DBselect(
				'SELECT COUNT(*) AS count'.
				' FROM opmessage'.
				' WHERE mediatypeid='.$mediatype['mediatypeid']
		));
		$usedInOperations = ($dbRow['count'] > 0);

		$this->zbxTestLogin('zabbix.php?action=mediatype.list');
		$this->zbxTestCheckboxSelect('mediatypeids_'.$mediatype['mediatypeid']);
		$this->zbxTestClickButton('mediatype.delete');
		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of media types');

		$sql = 'SELECT NULL FROM media_type WHERE mediatypeid='.$mediatype['mediatypeid'];

		if ($usedInOperations) {
				$this->zbxTestTextNotPresent('Media type deleted');
				$this->zbxTestTextPresent(['Cannot delete media type', 'Media types used by action']);
				$this->assertEquals(1, CDBHelper::getCount($sql));
		}
		else {
				$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'Media type deleted');
				$this->assertEquals(0, CDBHelper::getCount($sql));
		}
	}
}
