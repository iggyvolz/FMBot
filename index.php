<?php
require "vendor/autoload.php";
require "settings.php";
$phpbb=new phpbbRemoteApi\phpbbRemoteApi(FMBOT_URL,FMBOT_USER,FMBOT_PASSWORD);
function votecount($game)
{
  list($f,$t,$players,$moderators)=[$game->forum,$game->topic,$game->players,$game->moderators];
  @mkdir(".cache/$f",0777,true);
  if(!is_file(".cache/$f/$t"))
  {
    $data=["votes"=>[],"last"=>0,"md5"=>""];
  }
  else
  {
    $data=json_decode(file_get_contents(".cache/$f/$t"),true);
  }
  global $phpbb;
  $num=$phpbb->num_posts($f,$t);
  $votes=$data["votes"];
  $votecount=[];
  for($i=$data["last"];$i<$num;)
  {
    $posts=$phpbb->get_page($f,$t,$i);
    foreach($posts as $post)
    {
      list($author,$conts,$time)=[$post->author,$post->conts,$post->time];
      if(!isset($votes[$author]))
      {
        $votes[$author]=NULL;
      }
      if(stripos($conts,"/unvote")!==FALSE)
      {
        $votes[$author]=NULL;
      }
      if(stripos($conts,"/nolynch")!==FALSE)
      {
        $votes[$author]="No Lynch";
      }
      if(!isset($votes[$author]))
      {
        $votes[$author]=NULL;
      }
      if(in_array($author,$moderators) && stripos($conts,"/daystart"))
      {
        $votes=[]; // Reset votes
      }
      foreach($players as $player)
      {
        if(stripos($conts,"/vote $player")!==FALSE)
        {
          $votes[$author]=$player;
        }
      }
      $i++;
    }
  }
  foreach ($votes as $player => $vote)
  {
    if(!$vote)
    {
      continue;
    }
    if(!isset($votecount[$vote]))
    {
      $votecount[$vote]=[];
    }
    $votecount[$vote][]=$player;
  }
  $nliveplayers=count($players);
  $votecounttext="[votes=".$nliveplayers."]";
  $first=true;
  foreach($votecount as $player=>$pvotes)
  {
    if($first)
    {
      $first=false;
    }
    else
    {
      $votecounttext.=";";
    }
    $count=count($pvotes);
    $vvotes=implode($pvotes,", ");
    $votecounttext.="$player|$count|$vvotes";
  }
  $votecounttext.="[/votes]";
  if(md5($votecounttext)!==$data["md5"])
  {
    $phpbb->create_post($f,$t,"Vote Count",$votecounttext);
  }
  file_put_contents(".cache/$f/$t",json_encode(["md5"=>md5($votecounttext),"last"=>$num,"votes"=>$votes]));
}
while(!is_file(".kill"))
{
	$time=time()+60;
	foreach($games as $slug=>$game)
	{
		echo "$slug vote count\n";
		votecount($game);
	}
	if($time>time()) sleep($time-time());
}
@unlink(".kill");
