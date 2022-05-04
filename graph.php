<?php
include_once 'config.php';
?>
<!DOCTYPE html>
<!-- code by mnihyc -->
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    <title><?php echo SITE_NAME; ?>'s Network OSPF Graph</title>
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <style type="text/css">
      .mynetworkc {
        margin-left: 4px;
        height: 550px;
      }
      .btnswap {
        width: 30px;
        height: 30px;
      }
    </style>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.1.3/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.1.3/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
  </head>
  <body>
  	<div class="container">
  	  <h2 class="text-center"><?php echo SITE_NAME; ?>'s Network OSPF Graph</h2>
  	</div>
  	<div class="row">
  	  <div class="col-8">
        <div class="container border mynetworkc" id="mynetwork"></div>
      </div>
      <div class="col">
        <div class="row container align-items-center">
          <div class="col-5 text-end"><div id="select_ipf4">IPv4</div></div>
          <div class="col-2">
            <button type="button" class="btn btn-dark btn-sm" disabled id="button_exIPF" onclick="this.disabled=true;clearTextDetail();exSeleIPF();renderFunc(garr);">
              <i class="fa-solid fa-arrow-right-arrow-left"></i>
            </button>
          </div>
          <div class="col-5 text-start"><div id="select_ipf6">IPv6</div></div>
        </div>
        <div>&nbsp;</div>
        <div class="row container align-items-center">
          <h5 class="text-center">Path calculation</h5>
        </div>
        <div class="row container align-items-center">
          <div class="col-3">
            <select id="select_from" onchange="clearPath();"></select>
          </div>
          <div class="col-1">to</div>
          <div class="col-4">
            <select id="select_to" onchange="clearPath();"></select>
          </div>
          <div class="col-2">
            <button type="button" class="btn btn-dark btn-sm" onclick="exSele();clearPath();">
              <i class="fa-solid fa-arrows-rotate"></i>
            </button>
          </div>
          <div class="col-2">
            <button type="button" class="btn btn-dark btn-lg" onclick="showPath();">
              <i class="fa-solid fa-equals"></i>
            </button>
          </div>
        </div>
        <div>&nbsp;</div>
        <div class="row container align-items-center text-center">
          <h5 class="text-center">Detail</h5>
        </div>
        <div class="row container align-items-center text-start" id="textdetail">
        </div>
      </div>
    </div>
    <script type="text/javascript">
  var garr, gedges, gnetwork, gdnodes, gdedges, gsdist, gspath;
  var default_color_blue='#2B7CE9';
  var default_color_grey='#848484';
  var default_color_red='#ff3f3f';
  
  var ipf = '4';
  exSeleIPF();
  <?php if(!isset($_REQUEST['ipv6'])) { ?> exSeleIPF(); <?php } ?>
  
  var url = "<?php echo API_URL.$token; ?>";
  $.ajax({
    url: url,
    dataType: 'json',
    success:
      function(data, status) {
        if(data['status'] != 1) {
          alert(data['msg']);
          if(data['status'] == -1)
            window.history.back();
        }
        else {
          garr = data['msg'];
          if(renderFunc(garr) == 0) {
            exSeleIPF();
            if(renderFunc(garr) == 0)
                alert('no node info available');
          }
        }
      }
  });
  
  function findPeerNode(arr, intf) {
    let k=0;
    for(; k<arr.length; k++)
      if(arr[k]['name'] == intf['peer_name'])
        break;
    if(k>=arr.length)
      for(k=0; k<arr.length; k++)
        if(arr[k]['real_ip'] == intf['peer_real_ip'])
          break;
    return k;
  }
  
  function findEdge(arr, intfn, name) {
    for(let j=0; j<arr[intfn].length; j++)
      if(arr[intfn][j]['name'] == name)
        return arr[intfn][j];
    return null;
  }
  
  function findReverseEdge(arr, intfn, ge) {
    let idx = ge[1];
    for(let j=0; j<arr[idx][intfn].length; j++)
      if(findPeerNode(arr, arr[idx][intfn][j]) == ge[0]) {
        const intf = arr[idx][intfn][j];
        const gf = ge[2];
        if(intf['peer_type']==gf['peer_type'])
          return intf;
      }
    return null;
  }
  
  function renderFunc(arr) {
    const intfn = 'intf' + ipf;
    const cname = 'mynetwork';
    const sltfn = 'select_from';
    document.getElementById(sltfn).options.length = 0;
    const slttn = 'select_to';
    document.getElementById(slttn).options.length = 0;
    
    let nodes = new Array();
    let edges = new Array();
    gedges = new Array();
    let eid = 0;
    for(let i=0; i<arr.length; i++) {
      const intf = arr[i][intfn];
      if(intf.length == 0)
        continue;
      const ct = {id: i, label: arr[i]['name']};
      nodes.push(ct);
      for(let j=0; j<intf.length; j++)
    	if('cost' in intf[j]) {
    	  //const name = '(' + intf[j]['cost'].toString() + ')';
    	  //if(intf[j]['peer_type'].length)
    	  //  name = intf[j]['peer_type'] + ' ' + name;
    	  const name = intf[j]['cost'].toString();
    	  
    	  const k=findPeerNode(arr, intf[j]);
          if(k>=arr.length) {
              alert('peer info '+arr[i]['name']+'['+intf[j]['name']+'] not found');
              return;
          }
          
          let cl = {id: eid++, from: i, to: k, label: name};
          if('link_down' in intf[j] && intf[j]['link_down']==true) {
        	cl['color'] = default_color_grey;
        	cl['label'] += 'D';
          }
          edges.push(cl);
          gedges.push([i, k, intf[j]]);
    	}
    }
    
    let opts = nodes;
    opts.sort(function(a, b){
      if(a.label < b.label) { return -1; }
      if(a.label > b.label) { return 1; }
      return 0;
    });
    for(let i=0; i<opts.length; i++) {
      document.getElementById(sltfn).options.add(new Option(opts[i].label, opts[i].id));
      document.getElementById(slttn).options.add(new Option(opts[i].label, opts[i].id));
    }
    
    FloydSP();

    let container = document.getElementById(cname);
    let data = {
      nodes: gdnodes=new vis.DataSet(nodes),
      edges: gdedges=new vis.DataSet(edges)
    };
    let options = {
      nodes: {
        shape: 'box',
        font: {
          size: 20
        },
        color: {
          highlight: default_color_red,
          hover: default_color_red
        }
      },
      edges: { 
        arrows: 'to',
        font: {
          size: 20
        },
        endPointOffset: {
          to: 1
        },
        color: {
          color: default_color_blue,
          highlight: default_color_red,
          hover: default_color_red,
          inherit: false
        }
      },
      physics: {
        barnesHut: {
          avoidOverlap: 0.2
        },
        repulsion: {
          centralGravity: 0.2,
          springLength: 100,
          springConstant: 0.008,
          nodeDistance: Math.max(100, nodes.length * 10),
          damping: 0.08
        },
        forceAtlas2Based: {
         gravitationalConstant: -50,
         springLength: 250,
         springConstant: 0.015
        },
        solver: 'forceAtlas2Based'
      }
    };
    gnetwork = new vis.Network(container, data, options);
    gnetwork.on('selectNode', dispNodeDetail);
    gnetwork.on('selectEdge', dispEdgeDetail);
    gnetwork.on('deselectNode', deSeleDetail);
    gnetwork.on('deselectEdge', deSeleDetail);
    
    document.getElementById('button_exIPF').disabled = '';
    return nodes.length;
  }
  
  function exSele() {
    const sltfn = 'select_from';
    const slttn = 'select_to';
    const t = document.getElementById(sltfn).options.selectedIndex;
    const p = document.getElementById(slttn).options.selectedIndex;
    document.getElementById(sltfn).options.selectedIndex = p;
    document.getElementById(slttn).options.selectedIndex = t;
  }
  
  function exSeleIPF() {
    const sltipf = 'select_ipf' + ipf;
    let oipf = '4';
    if(ipf == '4')
      oipf = '6';
    const sltipfo = 'select_ipf' + oipf;
    const t = document.getElementById(sltipf);
    const o = document.getElementById(sltipfo);
    t.style.fontWeight = "normal";
    t.style.fontSize = "x-small";
    o.style.fontWeight = "bond";
    o.style.fontSize = "xx-large";
    ipf = oipf;
  }
  
  function clearTextDetail() {
    document.getElementById('textdetail').innerHTML = '';
  }
  
  function appendTextDetail(str) {
    document.getElementById('textdetail').innerHTML += '<div>' + str + '</div>';
  }
  
  function deSeleDetail(prop) {
    clearTextDetail();
  }
  
  function outputIP(nidx, ppd, name) {
<?php if(!isset($_REQUEST['showall'])) echo 'if(ipf == "4") {'; ?>
    let edge = findEdge(garr[nidx], 'intf4', name);
    if(edge != null)
      for(let i=0; i<edge['ip'].length; i++)
        appendTextDetail(ppd+edge['ip'][i]);
<?php if(!isset($_REQUEST['showall'])) echo '} if(ipf == "6") {'; ?>
    edge = findEdge(garr[nidx], 'intf6', name);
    if(edge != null)
      for(let i=0; i<edge['ip'].length; i++)
        appendTextDetail(ppd+edge['ip'][i]);
<?php if(!isset($_REQUEST['showall'])) echo '}'; ?>
  }
  
  function dispEdgeDetail(prop) {
    const intfn = 'intf' + ipf;
    if(prop.nodes.length > 0)
      return;
    if(prop.edges.length != 1)
      return;
    clearTextDetail();
    const arr = gedges[prop.edges[0]];
    appendTextDetail('Edge: from "'+garr[arr[0]]['name']+'" to "'+garr[arr[1]]['name']+'"');
    if('link_down' in arr[2] && arr[2]['link_down']==true)
      appendTextDetail('-- LINK: DOWN');
    appendTextDetail('-- Peer:  cost '+arr[2]['cost']+', name "'+arr[2]['name']+'"');
    appendTextDetail('-- IPs (src):');
    outputIP(arr[0], '---- ', arr[2]['name']);
    const rf = findReverseEdge(garr, intfn, arr);
    if(rf != null) {
      appendTextDetail('-- Reverse: (est.)');
      appendTextDetail('---- Peer:  cost '+rf['cost']+', name "'+rf['name']+'"');
      appendTextDetail('---- IPs:');
      outputIP(arr[1], '------ ', rf['name']);
    }
  }
  
  function dispNodeDetail(prop) {
    const intfn = 'intf' + ipf;
    if(prop.nodes.length != 1)
      return;
    clearTextDetail();
    const arr = garr[prop.nodes[0]];
    appendTextDetail('Node: "'+arr['name']+'", RouterID '+arr['router_id']);
    appendTextDetail('-- Last seen: '+getTimeInterval(parseInt(arr['last_updated'])*1000));
    appendTextDetail('-- IPs:');
    for(let i=0; i<arr[intfn].length; i++)
      if(!('cost' in arr[intfn][i])) {
        appendTextDetail('---- interface "'+arr[intfn][i]['name']+'"');
        outputIP(prop.nodes[0], '------ ', arr[intfn][i]['name']);
      }
  }
  
  function calcSP(path, s, d) {
  	if(gspath[s][d].length > 0)
  	  return gspath[s][d];
  	if(s == d)
  	  return gspath[s][d]=[[s]];
  	if(path[s][d].length == 0) {
  		alert('error computing shortest path');
  		return new Array();
  	}
  	for(let i=0; i<path[s][d].length; i++) {
  	  if(path[s][d][i] == -1) {
  	  	gspath[s][d].push([s, d]);
  	  	continue;
  	  }
  	  const sp = calcSP(path, s, path[s][d][i]);
  	  const dp = calcSP(path, path[s][d][i], d);
  	  for(let x=0; x<sp.length; x++)
  	    for(let y=0; y<dp.length; y++) {
  	      let spi = [...sp[x]];
  	      const dpi = dp[y];
  	      spi.pop();
  	      gspath[s][d].push(spi.concat(dpi));
  	    }
  	}
  	return gspath[s][d];
  }
  
  function FloydSP() {
  	gspath = new Array();
  	let path = new Array();
  	let dist = new Array();
  	for(let i=0; i<garr.length; i++) {
  	  gspath[i] = new Array();
  	  path[i] = new Array();
  	  dist[i] = new Array();
  	  for(let j=0; j<garr.length; j++) {
  	  	gspath[i][j] = new Array();
  	    path[i][j] = new Array();
  	    dist[i][j] = Infinity;
  	  }
  	  dist[i][i] = 0;
  	}
  	for(let j=0; j<gedges.length; j++)
  	  if(!('link_down' in gedges[j][2])) {
  	    dist[gedges[j][0]][gedges[j][1]] = gedges[j][2]['cost'];
  	    path[gedges[j][0]][gedges[j][1]] = [-1];
  	}
  	for(let k=0; k<garr.length; k++)
  	  for(let i=0; i<garr.length; i++)
  	    for(let j=0; j<garr.length; j++)
  	      if(i!=k && i!=j && k!=j)
  	        if(dist[i][k] + dist[k][j] < dist[i][j]) {
  	          path[i][j] = [k];
  	          dist[i][j] = dist[i][k] + dist[k][j];
  	        }
  	        else if(dist[i][k] + dist[k][j] == dist[i][j])
  	          path[i][j].push(k);
    for(let i=0; i<garr.length; i++)
      for(let j=0; j<garr.length; j++)
        if(dist[i][j] != Infinity)
          calcSP(path, i, j);
    gsdist = dist;
    clearPath();
  }
  
  var showPathCount = 0;
  function clearPath() {
  	showPathCount = 0;
  }
  
  function showPath() {
  	clearTextDetail();
  	let s = document.getElementById('select_from').selectedOptions[0].value;
  	let d = document.getElementById('select_to').selectedOptions[0].value;
  	s = parseInt(s); d = parseInt(d);
    appendTextDetail('Shortest path from "'+garr[s]['name']+'" to "'+garr[d]['name']+'"');
    appendTextDetail('-- total cost '+gsdist[s][d].toString()+', counts '+(showPathCount+1).toString()+'/'+gspath[s][d].length.toString());
    appendTextDetail('-- possible route');
    appendTextDetail('---- start "'+garr[s]['name']+'", total 0')
    var nodes=new Array(), edges=new Array(), total=0;
    const path = gspath[s][d][showPathCount];
    for(let i=0; i<path.length - 1; i++) {
      nodes.push(path[i]);
      for(var j=0; j<gedges.length; j++)
        if(path[i]==gedges[j][0] && path[i+1]==gedges[j][1] && gedges[j][2]['cost']==gsdist[path[i]][path[i+1]])
          edges.push(j);
      total += gsdist[path[i]][path[i+1]];
      //appendTextDetail('---- from "'+garr[path[i]]['name']+'" to "'+garr[path[i+1]]['name']+'", cost '+gsdist[path[i]][path[i+1]].toString()+', total '+total.toString());
      appendTextDetail('---- from "'+garr[path[i]]['name']+'" to "'+garr[path[i+1]]['name']+'", cost '+gsdist[path[i]][path[i+1]].toString());
    }
    nodes.push(d);
    appendTextDetail('---- end "'+garr[d]['name']+'", total '+gsdist[s][d].toString());
    gnetwork.setSelection({
    	nodes: nodes,
    	edges: edges
    }, {
    	unselectAll: true,
    	highlightEdges: false
    });
    //changeColor(nodes, edges, 'red');
    if(++showPathCount >= gspath[s][d].length)
      showPathCount = 0;
  }
  
  function changeColor(nodes, edges, color) {
  	let dnodes = gdnodes.get();
    for(let i=0; i<nodes.length; i++)
      for(let j=0; j<dnodes.length; j++)
        if(dnodes[j]['id'] == nodes[i])
          dnodes[j]['color'] = color;
    gdnodes.update(dnodes);
    let dedges = gdedges.get();
    for(let j=0; j<edges.length; j++)
      dedges[edges[j]]['color'] = color;
    gdedges.update(dedges);
  }
  
  function defaultColor() {
  	let dnodes = gdnodes.get();
  	for(let i=0; i<dnodes.length; i++)
  	  dnodes[i]['color'] = default_color_blue;
  	gdnodes.update(dnodes);
  	let dedges = gdedges.get();
    for(let j=0; j<dedges.length; j++)
      dedges[j]['color'] = default_color_blue;
    gdedges.update(dedges);
  }
  
  function getTimeInterval(date) {
    let seconds = Math.floor((Date.now() - date) / 1000);
    let unit = 'second';
    let value = seconds;
    if (seconds >= 31536000) {
      value = Math.floor(seconds / 31536000);
      unit = 'year';
    } else if (seconds >= 86400) {
      value = Math.floor(seconds / 86400);
      unit = 'day';
    } else if (seconds >= 3600) {
      value = Math.floor(seconds / 3600);
      unit = 'hour';
    } else if (seconds >= 60) {
      value = Math.floor(seconds / 60);
      unit = 'minute';
    }
    if (value != 1)
      unit = unit + 's';
    return value + ' ' + unit + ' ago';
  }
  
    </script>
  </body>
</html>