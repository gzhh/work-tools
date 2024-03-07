<?php
/*
 *  Apple Books (iBooks) notes to Markdown converter
 *  
 *  Reads the Books Annotations library on your Mac and exports
 *  them, tagged with their respective book title and imported in
 *  separate notebooks.
 *
 *  Usage:
 *  
 *  Move this script to the top of your personal home directory on your Mac.
 *  This is the folder that has your name, which the Finder opens if you
 *  click on the Finder icon in the Dock.
 *
 *  To export your notes to MD Files:
 *  
 *  1. Run the following command in the Terminal:
 *
 *     php ./book_notes_to_md.php
 *    
 *  2. Open the newly created "export"
 *
 */
 
// Default file locations for required iBooks data 
define('RESULT_DIRECTORY_NAME',"export");
define('BOOKS_DATABASE_DIRECTORY','~/Library/Containers/com.apple.iBooksX/Data/Documents/BKLibrary');
define('NOTES_DATABASE_DIRECTORY','~/Library/Containers/com.apple.iBooksX/Data/Documents/AEAnnotation');

// Verify presence of iBooks database

$path = exec('ls '.BOOKS_DATABASE_DIRECTORY."/*.sqlite");
define('BOOKS_DATABASE_FILE', $path);

// Verify presence of iBooks notes database
$path = exec('ls '.NOTES_DATABASE_DIRECTORY."/*.sqlite");
define('NOTES_DATABASE_FILE', $path);

// Fire up a SQLite parser
class MyDB extends SQLite3
{
  function __construct($FileName)
  {
     $this->open($FileName);
  }
}

// Retrieve any books.
$books = array();

$booksdb = new MyDB(BOOKS_DATABASE_FILE);

if (!$booksdb) {
  echo $booksdb->lastErrorMsg();
} 

$res = $booksdb->query("
			SELECT
				ZASSETID,
				ZTITLE AS Title,
				ZAUTHOR AS Author
			FROM ZBKLIBRARYASSET
			WHERE ZTITLE IS NOT NULL");

while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
	$books[$row['ZASSETID']] = $row;
}

$booksdb->close();

if (count($books) == 0) die("No books found in your library. Have you added any to iBooks?\n");

// Retrieve the notes.
$notesdb = new MyDB(NOTES_DATABASE_FILE);

if (!$notesdb) {
  echo $notesdb->lastErrorMsg();
} 

$notes = array();

$res = $notesdb->query("
			SELECT
				ZANNOTATIONREPRESENTATIVETEXT as BroaderText,
				ZANNOTATIONSELECTEDTEXT as SelectedText,
				ZANNOTATIONNOTE as Note,
				ZFUTUREPROOFING5 as Chapter,
				ZANNOTATIONCREATIONDATE as Created,
				ZANNOTATIONMODIFICATIONDATE as Modified,
				ZANNOTATIONASSETID
			FROM ZAEANNOTATION
			WHERE ZANNOTATIONSELECTEDTEXT IS NOT NULL
			ORDER BY ZANNOTATIONASSETID ASC,Created ASC");

while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
	$notes[$row['ZANNOTATIONASSETID']][] = $row;
}

$notesdb->close();


if(count($notes)==0) die("No notes found in your library. Have you added any to iBooks?\n\nIf you did on other devices than this Mac, make sure to enable iBooks notes/bookmarks syncing on all devices.");


// Create a new directory and cd into it
@mkdir(RESULT_DIRECTORY_NAME);
chdir(RESULT_DIRECTORY_NAME);

// echo '<pre>';
// print_r($books);
// print_r($notes);

foreach($notes as $AssetID => $booknotes){
	if (array_key_exists($AssetID, $books)) {
		$BookTitle = $books[$AssetID]['Title'] . " by " . $books[$AssetID]['Author'];
		$Body = "## $BookTitle\n\n";
	} else {
		$BookTitle = "Unknown book";
		$Body = "## Unknown book\n\n";
	}

	foreach($booknotes as $i => $note){
		// Skip empty notes
		if (strlen($note['BroaderText'] ? $note['BroaderText']:$note['SelectedText']) == 0) continue;
		
		$HighlightedText = $note['SelectedText'];
		$lines = explode("\n", $HighlightedText);
		foreach ($lines as &$line) {
			$line = '> ' . $line;
		}
		$HighlightedText = implode("\n", $lines);
		
		if (!empty($note['Chapter'])) {
			if ($i == 0 || $booknotes[$i-1]['Chapter'] != $booknotes[$i]['Chapter']) {
				$Body .= "### Chapter: " . $note['Chapter'] . "\n\n";
			}
		}
		$Body .= @strftime('%Y-%m-%d %H%:%M',@strtotime("2001-01-01 +". ((int)$note['Created'])." seconds")) . "\n";
		$Body .= "$HighlightedText\n";
		if (!empty($note['Note'])) {
			$Body .= "\n" . $note['Note'] . "\n";
		}
		// if (!empty($note['BroaderText']) && $note['BroaderText'] != $note['SelectedText']) {
		// 	$Body .= "Context: " . $note['BroaderText'] . "\n";
		// }

		// $Body .= "\n---\n\n";
		$Body .= "\n\n";
	}
	file_put_contents($BookTitle.".md", $Body);
}

echo "Done!\n";
