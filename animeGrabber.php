<?php
class animeGrabber{
	private $base_url = "https://otakotaku.com/";


	private function get_content($url) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://otakotaku.com/".$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);       
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}

	private function get_multi_content($array){
		foreach($array as $k => $url){
			$ch[$k] = curl_init();
			curl_setopt($ch[$k], CURLOPT_URL, "https://otakotaku.com/".$url);
			curl_setopt($ch[$k], CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($ch[$k], CURLOPT_FOLLOWLOCATION, TRUE); 
		}
		$mh = curl_multi_init();


		foreach($ch as &$c){
			curl_multi_add_handle($mh,$c);
		}

		do {
			$status = curl_multi_exec($mh, $active);
			if ($active) {
				curl_multi_select($mh);
			}
		} while ($active && $status == CURLM_OK);

		foreach($ch as &$c){
			$html[] = curl_multi_getcontent($c);
			curl_multi_remove_handle($mh,$c);
		}
		curl_multi_close($mh);
		return $html;

	}

	private function getByHTMLcontent($data,$selector,$tag,$id,$strip=true){
		preg_match('~<'.$tag.' '.$selector.'="'.$id.'"[^>]*>(.*?)</'.$tag.'>~si', $data, $dealer_price);
		if($strip){
			return strip_tags(trim($dealer_price[1]));
		}else{
			return (trim($dealer_price[1]));
		}
	}

	private function getByHTMLattr($data,$attr){
		preg_match( '/'.$attr.'="(.*?)"/i', $data, $array ) ;
		return $array[1];
	}

	private function getByTable($data,$selector,$value){
		$cell=array();
		$tt = 0;
		$rr = 0;
		$cc = 0;
		preg_match_all('#<table '.$selector.'="'.$value.'"[^>]*>(.*?)</table[^>]*>#is', $data, $t_matches, PREG_PATTERN_ORDER);
		foreach ($t_matches[1] as $tablestring){
			preg_match_all('#<tr[^>]*>(.*?)</tr[^>]*>#is', $tablestring, $tr_matches, PREG_PATTERN_ORDER);
			foreach($tr_matches[1] as $rowstring){
				preg_match_all('#<td[^>]*>(.*?)</td[^>]*>#is', $rowstring, $td_matches, PREG_PATTERN_ORDER);
				foreach($td_matches[1] as $cellstring){
					$cell[$tt][$rr][$cc] = trim($cellstring);
					$cc++;
				} 
				$rr++;
				$cc=0;
			}
			$tt++;
			$rr=0;
		}
		return $cell;
	}

	private function getAnimeSeason(){
		$month = date('m');
		if($month>=3&&$month<=5){
			$season = "spring";
		}elseif($month>=6&&$month<=8){
			$season = "summer";
		}elseif($month>=9&&$month<=11){
			$season = "fall";
		}else{
			$season = "winter";
		}
		return $season;
	}

	private function dateSystem($date){
		$d = explode(' ', $date);
		$tahun = $d[2];
		$bulan = $d[1];
		switch ($bulan) {
			case 'Jan':
			$bulan = "01";
			break;
			case 'Feb':
			$bulan = "02";
			break;
			case 'Mar':
			$bulan = "03";
			break;
			case 'Apr':
			$bulan = "04";
			break;
			case 'Mei':
			$bulan = "05";
			break;
			case 'Jun':
			$bulan = "06";
			break;
			case 'Jul':
			$bulan = "07";
			break;
			case 'Agu':
			$bulan = "08";
			break;
			case 'Sep':
			$bulan = "09";
			break;
			case 'Okt':
			$bulan = "10";
			break;
			case 'Nov':
			$bulan = "11";
			break;
			case 'Des':
			$bulan = "12";
			break;
		}
		$tanggal = $d[0];
		return $tahun."-".$bulan."-".$tanggal;
	}

	private function fetchAnimeDetail($data){
		$judul = $this->getByHTMLcontent($data,"id","h1","judul_anime");
		$sinopsis = $this->getByHTMLcontent($data,"id","div","sinopsis");
		$cover = $this->getByHTMLcontent($data,"class","div","cover-content",false);
		$cover = $this->getByHTMLattr($cover,"src");
		$table = $this->getByTable($data,"class","table-detail");
		$result = array();
		$result['sinopsis'] = $sinopsis;
		$result['cover'] = $cover;
		foreach($table as $row){
			foreach($row as $r){
				$name = str_replace(" ", "_", strtolower($r[0]));
				$content = ltrim(strip_tags(($r[1])));
				$content = $content=="?" ? '-' : $content;
				if($name=="genre"){
					$genre = explode(',', $content);
					$content = array();
					foreach($genre as $g){
						$content[] = trim($g);
					}
				}
				if($name=="tayang"){
					$tayang = explode('-', $content);
					$content = array();
					$content['awal'] = trim($tayang[0])=="?" ? '-' : $this->dateSystem($tayang[0]);
					if(isset($tayang[1])){
						$content['akhir'] = trim($tayang[1])=="?" ? '-' : $this->dateSystem($tayang[1]);
					}else{
						$content['akhir'] = '-';
					}
				}
				$result[$name] = $content;
			}
		}
		return (object) $result;
	}


	function getAnimeByID($id){
		$found = true;
		if($found){
			$data = $this->get_content("anime/view/$id");
			$result = $this->fetchAnimeDetail($data);
			return $result;
		}else{
			return false;
		}
	}

	function getAnimeByArrayOfID($array){
		foreach($array as $k => $a){
			$array[$k] = "anime/view/$a";
		}
		$request = $this->get_multi_content($array);
		$result = array();
		foreach($request as $r){
			$result[] = $this->fetchAnimeDetail($r);
		}
		return $result;
	}

	function searchAnime($keyword){
		$keyword = urlencode($keyword);
		$data = $this->get_content("search?q=$keyword");
		preg_match_all('#<div class="anime-result">\s*(<div.*?</div>\s*)</div>#is', $data, $search_result);
		if(isset($search_result[1][0])){
			preg_match_all('#<div class="anime-grid">(<div.*?</div>\s*)?(.*?)</div>#is', $search_result[1][0], $anime_grid);
			$c = array();
			foreach($anime_grid[0] as $a){
				preg_match('#<a[^>]*>#is', $a, $url);
				$code = $this->getByHTMLattr($url[0],"href");
				$code = explode("/", $code)[5];
				$c[] = $code;
			}
			$result = $this->getAnimeByArrayOfID($c);
			return $result;
		}else{
			return false;
		}
	}

	function getNewestAnime($limit=0){
		$season = $this->getAnimeSeason();
		$year = date('Y');
		$data = $this->get_content("anime/season/$season-$year");
		preg_match_all('#<[^>]* class="col-anime-data">\s*(<div.*?\s*(<div.*?\s*(<div.*?</div>\s*)</div>\s*)</div>\s*)</[^>]*>#is', $data, $search_result);
		preg_match_all('#<div class="col-anime">\s*(<div.*?</div>\s*)?(.*?)</div>#is', $search_result[1][0], $anime_grid);
		$c = array();
		$j=1;
		foreach($anime_grid[1] as $a){
			preg_match('#<a[^>]*>#is', $a, $url);
			$code = $this->getByHTMLattr($url[0],"href");
			$code = explode("/", $code)[5];
			$c[] = $code;
			$j++;
			if($limit!=0){
				if($j>$limit){
					break;
				}
			}
		}
		$result = $this->getAnimeByArrayOfID($c);
		return $result;
	}



}