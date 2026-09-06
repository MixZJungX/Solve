<?php $db=new PDO("sqlite:data/highspec.db"); print_r($db->query("SELECT * FROM members LIMIT 1")->fetchAll(PDO::FETCH_ASSOC));
