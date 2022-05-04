**OSPF Network Graph Visualization For Bird2**
 - Demo link [click here](https://test.mnihyc.com/ospf/graph.php?token=12345678&ipv6)
 - Not finished yet, still under development

**Synchronize between clients and backend**
 - `bird2/check_update.sh`
 - 1. Run as root to submit data (maybe crontab)
 - 2. Modify `$url` to the correct backend address
 - 3. Modify `$token` to the correct `VER_UPD_TOK`
 - `bird2/predef.conf`
 - 1. Must be placed in `/etc/bird/predef.conf`
 - 2. The first line must be `router id x.x.x.x;` (PRIMARY KEY)
 - `bird2/ospf.conf`
 - 1. Must be placed in `/etc/bird/ospf.conf`
 - 2. Support only `bird2` and `ospf v3`
 - 3. Name is obtained from the latest `protocol` name in file like `ospfX`, in which `ospf` is identical and `X` is global name of the node, such as `ospflax2`
 - 4. An interface containing `type ptp;` will be recognized as peering interfaces, of which `cost Z;` is processed
 - 5. Name of a peering interface is like `YpeerX`, in which `peer` is identical, `Y` is peering type and `X` is name of the peering node, such as `wgpeerhk`
 - 6. Opposing to 4., name of an non-peering interface can be arbitrary.

**Backend configuration**
 - `config.example.php`
 - 1. Modify as comments in the file
 - 2. Rename to `config.php` after configuration
 - `backend/config.example.php`
 - 1. Modify as comments in the file
 - 2. Rename to `config.php` after configuration
 - `backend/db.config.sqlite`
 - 1. A demo database used in the demo link above
 - 2. Rename to `db.sqlite` for test only

**Development**
 - Index page not available yet
 - Parameters of `graph.php`
 - 1. `token`, must equal to `VER_GET_TOK`
 - 2. `ipv6`, optional, defaults to IPv6 graph when set
 - 3. `showall`, optional, show all IPs regardless of current IPv4/6

**Upgradation**
 - Delete `backend/db.sqlite` (wait for new submissions to fill in) to prevent database errors

