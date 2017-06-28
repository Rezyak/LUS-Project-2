<?php
/***
 * FST-based SLU
 */

require_once 'FstUtilities.php';

class FstSlu extends FstUtilities {

	private $wfst;
	private $lm;
	protected $lex;                // lexicon as array
	private $unk;
	private $ilex;
	private $olex;
	private $fsmout = '/tmp/str.fsm'; // output file name for text FST
	private $fstout = '/tmp/str.fst'; // output file name for compiled FST

	public function __construct($wfst, $lm, $ilex, $olex, $unk = '<unk>') {
		$this->wfst = $wfst;
		$this->lm   = $lm;
		$this->ilex = $ilex;
		$this->olex = $olex;
		$this->unk  = $unk;

		// read lexicon for checking for unknown words
		$this->readLexicon($ilex);
	}

	/**
	 * run SLU
	 *
	 * @param  string $input
	 * @return array  $results
	 */
	private function FstSluParse($input) {

		$this->text2fsm($input, $this->fsmout, $this->unk);
		$this->FstCompile($this->fsmout, $this->fstout, $this->ilex, $this->olex, FALSE);

		// compile pipeline
		$cmd  = "/usr/local/bin/fstcompose $this->fstout $this->wfst | ";
		$cmd .= "/usr/local/bin/fstcompose - $this->lm ";
		$cmd .= '| /usr/local/bin/fstrmepsilon | ' .'/usr/local/bin/fstshortestpath  --nshortest=3 | ' ;
		$cmd .= $this->fstprintstrings($this->ilex, $this->olex);

		exec($cmd, $out);

		// parse output
		$results = array();
		foreach ($out as $line) {
			$la = explode("\t", trim($line));
			//$lbl_arr = preg_split('/\s/u', $la[1], -1, PREG_SPLIT_NO_EMPTY);
			$weight  = $la[2];
			$results[$la[1]] = $weight;
		}

		return $results;
	}

	/**
	 * Run SLU meta
	 *
	 * @param  string  $input input string
	 * @param  bool    $conf  whether to output confidences
	 * @param  mixed   $nbest whether to output nbest (number)
	 *
	 * @return mixed
	 */
	public function runSlu($input, $conf=FALSE, $nbest=FALSE) {
		$out = $this->FstSluParse($input);


		$confs = $this->computeConfidences($out);
		// var_dump($confs);

		arsort($confs);
		// var_dump($confs);
		

		// get nbest list
		if ($nbest) {
			$results = array_slice($confs, 0, $nbest);
		}
		else { // 1st best
			$results = array_slice($confs, 0, 1);
		}

		if (!$conf && $nbest) { // array of labels
			$results = array_keys($results);
		}
		elseif (!$conf && !$nbest) { // just label
			$results = array_keys($results);
			$results = $results[0];
		}
		else {
			$tmp = array();
			foreach ($results as $k => $v) {
				$tmp[] = array($k, $v);
			}
			$results = $tmp;
		}

		return $results;
	}
}

/***
 * Example Usage
 *
 * -w WFST
 * -l LM
 * -s string for classification
 * -i input lexicon file
 * -o output lexicon file    [optional, uses input, if not set]
 * -u unknown symbol         [optional]
 */
/*
$args = getopt('w:l:s:i:o:u:');
$wfst = $args['w'];
$lm   = $args['l'];
$unk  = (isset($args['u'])) ? $args['u'] : '<unk>';
$ilex = $args['i'];
$olex = (isset($args['o'])) ? $args['o'] : $args['i'];
$str  = trim($args['s']);

$SLU = new FstSlu($wfst, $lm, $ilex, $olex, $unk);
echo $SLU->runSlu($str) . "\n";
print_r($SLU->runSlu($str, TRUE));
print_r($SLU->runSlu($str, TRUE, 2));
print_r($SLU->runSlu($str, FALSE, 2));
*/
