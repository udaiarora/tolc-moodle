<?php

function score($sentence)
{
	$scores_file = file_get_contents("../../emotion.json");
	$scores = json_decode($scores_file, true);

	//$sample = "this is so cool and awesome dude.";

	$arr = explode(" ", $sentence);

	$total_score = 0;
	$not = 0;
	foreach ($arr as $word) {
		$word_last=substr($word, 0, -1);
		$word_lasttwo=substr($word_last, 0, -1);
		$word_lastthree=substr($word_lasttwo, 0, -1);
		$score=0;
		if(!empty($scores[$word]))
		{
			$score = $scores[$word];
		}
		elseif(!empty($scores[$word_last]))
		{
			$score = $scores[$word_last];
		}
		elseif(!empty($scores[$word_lasttwo]))
		{
			$score = $scores[$word_lasttwo];
		}
		elseif(!empty($scores[$word_lastthree]))
		{
			$score = $scores[$word_lastthree];
		}
		if($not==0)
		{
			$total_score += $score;
		}
		else
		{
			$total_score -= $score;
			if($score!=0)
				$not = 0;
		}
		if($word == 'not')
		{
			$not = 1;
		}
	}
	return $total_score;
}

?>