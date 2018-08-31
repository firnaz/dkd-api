<?php
class AppController{
	public function index(){
		echo "Data Keuangan Pemerintah Daerah API v 1.0";
	}
	public function tahun(){
		$sql = "select tahun as kode, tahun as name from tahun";
		$result = getDatabase()->all($sql);
		echo json_encode($result);
	}
	public function indikator(){
		$sql = "select * from v_indikator";
		$result = getDatabase()->all($sql);
		foreach($result as $key=>$val){
			$result[$key]["name"] = ucwords(strtolower($val["name"]));
		}
		echo json_encode($result);
	}
  	public static function region($jenis){
  		if ($jenis=="pemda"){
			$sql = "select kode_daerah as kode, nama_daerah as name, 'pemda' as tipe from pemda order by name";	  			
  		}else{
			$sql = "select kode_prov as kode, concat('Se-Provinsi ',nama_prov) as name, 'provinsi' as tipe from provinsi order by name";	  				  			
  		}
		$result = getDatabase()->all($sql);
		echo json_encode($result);
	}
	public static function grafik($type){
		$dataregion = json_decode($_GET['region']);
		$datatahun = json_decode($_GET['tahun']);
		$dataindikator = json_decode($_GET['indikator']);
		$jenis = $_GET['jenis'];

		$result = array();
		$k=0;
		if($type=="region"){
			foreach($dataregion as $region){
				$result[$k]= array();
				$result[$k]['xtitle'] = $region->name;
				$j=0;
				foreach($datatahun as $tahun){
					$result[$k]['data'][$j]['tahun']= $tahun->name;
					$i=1;
					foreach($dataindikator as $indikator){
						$result[$k]['ytitle'][$i-1]= ucwords(strtolower($indikator->name));
						$result[$k]['data'][$j]["data".$i] = self::getNilai($indikator->kode, $indikator->table, $region->kode, $region->tipe, $tahun->kode, $jenis);
						$i++;
					}
					$j++;
				}
				$result[$k]['ycount']=--$i;
				$k++;
			}
		}else{
			foreach($dataindikator as $indikator){
				$result[$k]= array();
				$result[$k]['xtitle'] = ucwords(strtolower($indikator->name));
				$j=0;
				foreach($datatahun as $tahun){
					$result[$k]['data'][$j]['tahun']= $tahun->name;
					$i=1;
					foreach($dataregion as $region){
						$result[$k]['ytitle'][$i-1]= $region->name;
						$result[$k]['data'][$j]["data".$i] = self::getNilai($indikator->kode, $indikator->table, $region->kode, $region->tipe, $tahun->kode, $jenis);
						$i++;
					}
					$j++;
				}
				$result[$k]['ycount']=--$i;
				$k++;
			}			
		}
		echo json_encode($result);
	}
	private function getNilai($indikator, $table, $daerah, $tipe, $tahun, $jenis){
		if ($table=="ikkd"){
			return self::getIkkd($indikator, $daerah, $tipe, $tahun, $jenis);
		}
		// Where Region
		if($tipe == 'provinsi'){
			$whereRegion = "AND kode_prov='$daerah' ";  
		}else{
			$whereRegion = "AND kode_daerah='$daerah' ";  			
		}

		// Where fungsi
		if($table=='fungsi'){
			$whereIndikator = "AND kode_fungsi='$indikator' ";
		}elseif($table == 'keuangan'){
			$sql = "select * from `$table` where kode='$indikator'";
			$result = getDatabase()->one($sql);
			$whereIndikator = "AND ".$result['field']."='$indikator'";
		}elseif($table == 'rekening'){
			$whereIndikator = "AND kd_rekening='$indikator'";
		}

		// Where tahun
		$whereTahun = "AND tahun='$tahun'";

		// Where jenis
		$whereJenis = "AND jenis_laporan='$jenis'";

		// Query
		if($table=='rekening'){
			$sql = "select ifnull(sum(nilai),0)/1000000 as nilai from neraca where 1=1 $whereRegion $whereIndikator $whereTahun";
		}else{
			$sql = "select ifnull(sum(nilai),0)/1000000 as nilai from apbd where 1=1 $whereRegion $whereIndikator $whereTahun $whereJenis";			
		}
		// echo $sql."<br>";
		$result = getDatabase()->one($sql);

		return $result['nilai'];
	}
	private function getIkkd($indikator, $daerah, $tipe, $tahun, $jenis){

		if($tipe == 'provinsi'){
			$whereRegion = "AND kode_prov='$daerah' ";  
		}else{
			$whereRegion = "AND kode_daerah='$daerah' ";  			
		}

		// Where tahun
		$whereTahun = "AND tahun='$tahun'";

		// Where jenis
		$whereJenis = "AND jenis_laporan='$jenis'";

		// Indikator
		$sql = "select * from ikkd where kode='$indikator'";
		$res = getDatabase()->one($sql);

		$rumus = $res['rumus'];

		preg_match_all("|{(.*)}|U", $rumus, $out, PREG_PATTERN_ORDER);
		foreach($out[1] as $val){
			$variable = $val;
			$komponen = explode(":",$variable);

			$whereIndikator = "AND ".$komponen[0]."='".$komponen[1]."'";

			$sql = "select ifnull(sum(nilai),0) as nilai from apbd where 1=1 $whereRegion $whereIndikator $whereTahun $whereJenis";
			$res = getDatabase()->one($sql);
			$rumus = str_replace("{".$variable."}",$res['nilai'],$rumus);
		}
		return self::calculate_string($rumus) * 100;
	}

	// calculate math from string downloaded from http://www.website55.com/php-mysql/2010/04/how-to-calculate-strings-with-php.html
	private function calculate_string( $mathString )    {
		// echo $mathString."<br><br>";
	    $mathString = trim($mathString);
	    $mathString = ereg_replace ('[^0-9\+-\*\/\(\) ]', '', $mathString);	 
	    $compute = create_function("", "return (@" . $mathString . ");" );
	    return 0 + $compute();
	}
	public static function displaychart(){
		// $config = $_GET['config'];
		$title = $_GET['title'];
		$jenis_laporan = $_GET['jenis_laporan'];
		$c = json_decode($_GET['config']);
		$c->legend->maxWidth=215;
		$c->legend->itemCls="legend-item";
		$c->axes[0]->renderer = "%renderer%";
		$json = str_replace("\"%renderer%\"", "function(label){ return numeral(label).format('0,0');}", json_encode($c));

		// print_r($c);exit;
		$js = 'var chart= Ext.create("Ext.chart.CartesianChart", '.$json.');
			var container = Ext.create("Ext.Container", {
				layout:"fit",
				height:800,
				width:800,
				items:[
					{
						xtype:"label",
						html: "'.$title.'",
						cls:"chart-title",
						docked:"top"
					},
					chart
				],
				renderTo:"share"
			});'; 
		echo '<!DOCTYPE HTML>
			<html manifest="" lang="en-US">
			<head>
			    <meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
				<meta name="apple-mobile-web-app-capable" content="yes">    
				<title>Data Keuangan Daerah</title>
				<!-- css -->
				<link rel="stylesheet" href="assets/css/app.css" />
				<link rel="stylesheet" href="assets/css/dkd.css" />

				<!-- js -->
			    <script type="text/javascript" src="assets/js/sencha-touch-all-debug.js"></script>
			    <script type="text/javascript" src="assets/js/numeral.min.js"></script>
			</head>
			<body>
				<div id="share" style="display:block;"></div>
				<script type="text/javascript">
					'.$js.'
				</script>
			</body>
			</html>';
	}
	public static function displaytable(){
		$store = json_decode($_GET['store']);
		$columns = json_decode($_GET['columns']);
		$title = $_GET['title'];
		$jenis_laporan = $_GET['jenis_laporan'];
		$table = "<table class='CSSTableGenerator'>";
		$table .= "<tr><td>Tahun</td>"; 
		foreach($columns as $column){
			$table.="<td>".$column->text."</td>";
		}
		$table .= "</tr>"; 

		foreach($store->data as $row){
			$r="";
			for ($i=0;$i<count($columns);$i++){
				$r.= "<td align='right'>".number_format($row->{"data".($i+1)})."</td>";
			}
			$table.="<tr>
						<td>$row->tahun</td>
						$r
					</tr>";
		}
		$table .= "</table>";

		echo '<!DOCTYPE HTML>
			<html manifest="" lang="en-US">
			<head>
			    <meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
				<meta name="apple-mobile-web-app-capable" content="yes">    
				<title>Data Keuangan Daerah</title>
				<!-- css -->
				<link rel="stylesheet" href="assets/css/app.css" />
				<link rel="stylesheet" href="assets/css/dkd.css" />

				<!-- js -->
			    <script type="text/javascript" src="assets/js/sencha-touch-all-debug.js"></script>
				<style>
					#container{
						width:800px;
					}
					h3 {
					    font-weight: bold;
					    text-align: center;
					    margin-bottom: 10px;
					}		
				</style>			
			</head>
			<body>
				<div id="container">
					<h3>'.$title.'</h3>
					'.$table.'
				</div>
			</body>
			</html>';
	}

	public static function getchartimage(){
		$config = $_GET['config'];
		$title = $_GET['title'];
		$jenis_laporan = $_GET['jenis_laporan'];
		$bin = "/usr/local/bin/wkhtmltoimage";
		$host = dirname("http://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
		$url = $host."/displaychart?config=".urlencode($config)."&title=".urlencode($title)."&jenis_laporan=$jenis_laporan";
		$file = md5(date("YmdHis")).".jpg";
		$path = "/tmp/$file";
		$command = "$bin \"$url\" $path";
		// echo $command;
		exec($command);
		$type = pathinfo($path, PATHINFO_EXTENSION);
		$data = file_get_contents($path);
		$base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
		@unlink($path);
		echo $base64;
	}

	public static function gettableimage(){
		$store = $_GET['store'];
		$columns = $_GET['columns'];
		$title = $_GET['title'];
		$jenis_laporan = $_GET['jenis_laporan'];
		$bin = "/usr/local/bin/wkhtmltoimage";
		$host = dirname("http://" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']);
		$url = $host."/displaytable?store=".urlencode($store)."&title=".urlencode($title)."&columns=".urlencode($columns)."&jenis_laporan=$jenis_laporan";
		$file = md5(date("YmdHis")).".jpg";
		$path = "/tmp/$file";
		$command = "$bin \"$url\" $path";
		// echo $command;
		exec($command);
		$type = pathinfo($path, PATHINFO_EXTENSION);
		$data = file_get_contents($path);
		$base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
		@unlink($path);
		echo $base64;		
	}
}
?>