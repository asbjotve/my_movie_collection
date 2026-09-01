/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.6-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: db_mediearkiv
-- ------------------------------------------------------
-- Server version	11.8.6-MariaDB-ubu2404

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `disc_in`
--

DROP TABLE IF EXISTS `disc_in`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `disc_in` (
  `copy_id` int(11) NOT NULL,
  `collection_id` binary(16) NOT NULL,
  `disc_id` binary(16) NOT NULL,
  `box_set_disc_order` int(11) DEFAULT NULL,
  `related_content_id` binary(16) DEFAULT NULL,
  PRIMARY KEY (`collection_id`,`copy_id`,`disc_id`) USING BTREE,
  UNIQUE KEY `uk_disc_in__disc_id` (`disc_id`) USING BTREE,
  UNIQUE KEY `uk_disc_in__copy_order` (`collection_id`,`copy_id`,`box_set_disc_order`) USING BTREE,
  KEY `idx_disc_in__related_content_id` (`related_content_id`),
  CONSTRAINT `fk_disc_in__content` FOREIGN KEY (`related_content_id`) REFERENCES `content` (`content_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_disc_in__disc` FOREIGN KEY (`disc_id`) REFERENCES `disc` (`disc_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_disc_in__physical_copy` FOREIGN KEY (`collection_id`, `copy_id`) REFERENCES `physical_copy` (`collection_id`, `copy_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disc_in`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `disc_in` WRITE;
/*!40000 ALTER TABLE `disc_in` DISABLE KEYS */;
INSERT INTO `disc_in` VALUES
(1,'\0®ßOÑ³öi¡ÉÊ›',' ¹‰]¸Gk©àv«“%ú',1,'–ÃíÉL>Oú¤Tœ½‘'),
(1,'\0rßv@À\n V°Gª^','M’CÖNŠ’´,Ô»\r¥Ì',1,'Ëu/cX\ZE7«¾Ü×…H'),
(1,'\0”DÔiLö‹¶°-0­é¡','ò3Ñ‹Ú6D{–´ek–ÕÊ',1,'è·½DH| Ê8idËù'),
(1,'\0âÕ]ccJH¬×ëf(Îxğ','\ZÂ‘¤Hä°Ï`®Q',1,'w¿„_ÈD¤©,*tlN|'),
(1,'#‚*øIÖ²sê§â‡‚','‰ÛJ1H ¢Éÿ²YÖš',1,'Xçè·öOé…ÇkÃÄˆn'),
(1,'{şİ®G¤‚~§ÕÇåï','uœ&ÒE¤³üÅğ¾Ş«á',1,'â¯ôÖçÍHæ„^“°tÒ'),
(1,'îºÓ­¸MY¹š)ØašK','xÆ,ş6B-¶Ç}è†ÎR',1,'Â_ÑÃ+*BIº*6~I±5'),
(1,'UŞ«øFj¢­Ù,ã&Ò\r','´”Ï¥ñCŞ¸+Â‚_“Ô',1,'7è!RÁBÉ˜¸~çà^'),
(1,'õBKÁ8I²àù[ÿW','Ğ° MAGLÔİ*µS+&',1,'uÖ9bË‹Duş0®æno'),
(1,'ŸåMCA¥€X_««Xj','‚àÑEß¥Ix@=`©',1,'‹(\ZãÍK““b\\_G#å'),
(1,'4Ÿòè@¦´¥wdHL','ºÎXñH@á*Õ`X+#Á',1,'ªıëä§çFœÁ—õi²]Œ'),
(1,'Û“ÛÕN>²™Ó\'É•(Ç','yÆ®tÑhLÀ»É%j·ŸÙ',1,'e‚cå‰IB–0n‘¹WÃ™'),
(1,'áU±fBN«\'~®ÁÖ}‰','®GÑÎqB¤=1×…Ü',1,'Hı]\0íbDd_[Û¨@ˆ'),
(1,'æì.O·­`Y-Jæ1g','ª¸í¿NFe·ñÂ¤ãï\"ñ',1,'±xbŞêH œahÑ3k+‡'),
(1,'	3Ñ,Ì„O£8ŠÕ“PX','î½<©…O.¥PÖ–ô‰@È',1,'~d“øDFŒjÕ°æ—0'),
(1,'<G\\«WNó¢¬ºUıæé','6NÜ˜¢C…kŠL_ªÉ',1,'î•FvME»»¯âMº'),
(1,'ÛONkK÷¿@B­Ù@[','„ŞŸiHh‚·ñ(ÌÎDš',1,'>ƒ°ÓˆH2 NHn†\"a'),
(1,'òß›™M¬…8ƒayhˆ—','i“²tECŒÕk>g',1,'0»ˆÃ#J°ƒ‹XT4—ô™'),
(1,'\rĞzvàB}¥ºĞ-Mr£','¤\"q›w½JydqãÜë.',1,'’<I”VC·ê¦’r?J'),
(1,'\rhÅ;Kù®s}oWÙ','k•’çÃÉLæ·ì&‡zôp',1,'7‚&B[”Ïbc\Zb'),
(1,'\r€˜¢ÑÙL…—\'ÛP3â‚','iœ8n}ÂL÷šÎù­Î£€â',1,'P—»bäEœ­?êôß»ú'),
(1,'\r¯$u5×Aí¯@b\'ŞÒ','Ÿ	IdÌE\"¦×  -/É“',1,'Ã-÷ô®ïNa»D‹Å¨‘«O'),
(1,'\råO¶çC£‰iÉĞğE¹W','‚mñË¿Ö@¶©T¯b	u',1,'7òŞ¬xÈM9«öDCó·WÍ'),
(1,'i°°E,şúÇÄN\r','3è‡\"ÃïF/–¶¼ÿı»',1,'\'°ì¯O\0»õ§ÑàÚ\n'),
(1,'óâˆHŸ”Ô\n¢¥¢:+','WSÌ;.NÆ•‚k£¾ËP',1,'L9A^8ÃN¡¹X‡UÀLƒ'),
(1,'\\¯Ù8BI¶…z~ØôL','½0lR#K‘*9(Ù»ÛÔ',1,'œÖBj­Hê“‘[’´L'),
(1,'p(g3fD…­‚±\"8Ÿn','ï¥Ğ.A´¿,1ƒág«5',1,'kŞ¿«iG-¨}¾öTå¿\r'),
(1,'s°+WFa¡j\"ŒïAÅ','KÂÑö@î”I1ÌÕ®`',1,'ËÅe˜¢bFe¾Ìœ`’'),
(1,'úJñ·KC©Ğ›T½Ì ','~	’F‡>F´ï@SG',1,'Ién²€J™“Ê¿9Å\'ô8'),
(1,'ûƒÚÒrF–ÒÄ«@#õ','Œ?ïljBƒ€¿wõ1R³s',1,'ì8ğ\'ÌOá°¹ûóÆĞ¼'),
(1,'acRğMÖ”4¼)ù–Uù','Ì¯Úè´I]ŒZS:Ú†!3',6,'ØÎ3†°A—‘<t{±'),
(1,'N²—Ÿ5K¬§«VBÈ','Pè~¸J¤§w&U5Å',1,'œ-Ä–ñ’F—”¥#²É.'),
(1,'øVÀ‰jB„=ÑĞ\0ô','9G%û9İG³«ŠŸ#*…t©',1,'¬cp¾/ùKJ†MÏxøK'),
(1,'–ê	PBA0”¿¿yÕ¯$M','ö÷~É`KÕ“ —J´ş“',1,'¶Ÿø²O¶.mUm‚'),
(1,'ü¬tkøAÚ‹ÆP¤‡>ø','˜•Ê<Š2Ad“ŸÆ5²,¦+',1,'Úe?ÉpûA1¬Š—B¿“fœ'),
(1,'øåºEV‰\"\\µù$iJ','ÉìšW‰Dp¨*w×›\ršä',1,'¨¬siG\rˆÑ¡Õ¾dùë'),
(1,'ÑV²´Fš¢[5ÏbF¹','€­–âqXK7¹6»¼È4—',1,'äoURAC£rW£°¦K<'),
(1,')7váKÔ”BuN£-Å¦','ÁÇY³ƒCÓš‘ºïŞÉA',1,'K½\0=GB½–á¥‡,L'),
(1,'f‹?ZN[µO^B’wqµ','›Î¤z‚L±—å!€Ot±',1,'÷½éxJÀºsŞq|\0à*'),
(1,'lÙ¸ôóFSŸ¡¿ B¢','-o«‡YH‰sôôxw',1,'^ü”ÎAE¡q£eÚQ±P'),
(1,'ÕñÚ„L·½ÍÉ8{8¶','^õù©Lóˆ´T9•nP',1,'xª©ÔJ)˜eç@'),
(1,'š¿ÂLKèºY_^Ä¥ü','“9(¹Å·Dˆ­\"’ c*}Z',1,',$\Z´PLE*…Š\'¥y¹i'),
(1,'¦ƒADü¦e0>¶Ò','‡İòm†?Iˆ¥±Â_OÖt',1,'Øíô[N4H?‰#ÃKqÜ¢'),
(1,'vò\"”]N© >°3FEƒá','æ‚³Š~J‰ ÊKì	¨Oˆ',1,'ÁGhfâ‘F> Y×.8,¡ƒ'),
(1,'â¾3EM+¤qÒw<Djâ','	½ÎcşG¾›Øûï Z',1,'%¼³$¯ËHû¶\0/Ÿ!tV'),
(1,'ş@E¨ææúçtî','£¥ŠçLôL#€Yw§­®+',1,'ÃóİC}Kç…¨—~ŞhN'),
(1,'›‹«MH­4:÷¹Sá','f–©üB[¢)âoD·&\\',1,'õH³ºóE¶’uˆğ˜Òù'),
(1,'ÓävmoOé¤„òÒ…Y›','€™É&†M*ˆá1Wyá',1,'¼¼²(H#¹=ã¾œAA'),
(1,'â|¨eBƒ›ãG9™wp','¨>Éi¯§N—‘Ğ3¢à',1,'~Ü8èäE„—N~&¸Ác'),
(1,'è˜QZàEr¥Ù†ñ•¹','hùÀÀò¼A¶‰ã]sª·5',1,'!àåA¿“ÜV'),
(1,'tå2OKš–ƒ%F','—ù‘ş&L;¤ Q‰Ÿ»8',1,'Øà”r+N¯»~y2z+'),
(1,'u5’¾óI!šO£	»Jç','.ÓïCÇlCù)ñŒ¯©–I',1,'BCµù£G±´/kÉ­YåL'),
(1,'u×EÿLBi“W÷‚G.C','Fk ÄÜAJ…‘Å˜#ì9F',1,'œŠÈ<€GÀ£8hv—7è'),
(1,'\\ŠØ±ƒD©¯­­èKôü]','8ÕR?•@Øª»JEXo3',1,'¤ş\Z€G¨¡‘³|kNT_'),
(1,' Ç¦Ô.Kî¼²ñï¤¬','Wqÿú¿EÇªv¼$ÇÖ',1,'¢Tõ˜‚ÏI%ÀæW÷Ô‡Ş'),
(1,'!û€ë•NC…€a°Ü','Şx,ehIB‘àŞ–h›}',1,'™¡‘®/eBºUĞš5,Ü'),
(1,'!ë­GPßJÆ©Ûå[GêRí','ÎNgvq%Lï°ÔáÌÕN\0',1,'şâ*«i\rFmŒ\ZW-±úõ'),
(1,'!ïEébC¼˜õ{ahıêÆ','I‚‹Q\nªI—¿Ê*¦Ş=à¿',1,'€¨¸Íı”MÌŒÂçÔ'),
(1,'\"G¼w N–¥+:Óä_Pá','>	ÿ^5ÛK.¯’ÆÕtşt¯',1,'¬XıNe°®bŸ{j'),
(1,'\"u@G\"”¯Óí®­K','MØë9ÓãD€€£Û¼[>é',1,'ŠÂUíÔJÿœ”k\'oÚÏ–'),
(1,'\"©^ªYG‡ã„ªu','Ğ !œ‘™EÓ(ô¨rè',1,'dD†@d®\r/)4´‚'),
(1,'#_ğşÅ½C¡¶Ğrå[$','DİRş8BÎ¤S}°ôC>Í',1,')åJ=¶E	§±”ÓÀÉnş'),
(1,'#d¤cÒ HªŠOoÏjùë','ì¹œÎÒxDÈ¶jKÓ|+',1,'²pAÖ×Gè¿h–Ù}ÃI'),
(1,'$êOW¯!úğ[jš¾','çP¥?AF«¼ïuÄì#³z',1,')0D”N>“e9¦ÌÑö'),
(1,'$8Ôp¬LÊ½Êùßƒêûé','#„¸¬¾H~‘â)ÓoIWŠ',1,'æÙÁ…üJÁ»½\rI[œi'),
(1,'$ú¬‰â*J6>_VA íJ','º,\0‡Oxá3;aÔVj',1,'!6G½±v5{ºŠøÆ'),
(1,'%Ô!ÑO·©tZ f8dö','Ö¬i—d\rA±œMV.‡m™',1,'x¡Ëj«HÚœÆåà\"{©'),
(1,'%3¬zyaL˜¶b<§ÃÎTl','5ÿcLšRNÊ©}İb¼şa',1,'ÇæjsŞH=Œ˜{Œ?ÒÏ'),
(1,'%Lf™–.Bi‹P((¯d÷','ÅÓElÖOöˆÃ`ƒÉp',1,'§ËË,–€CS„iz\'ÀÀ¼'),
(1,'%gÈèfD‹¨\'üÂ¾„#','\'ãÇ›¡÷Mù ·¤›@ªé',1,'DÏ¾EZ@íBìûçQh'),
(1,'%É¥UÖNıºá>¦ ¼¹','=™ımÀF…’ù*Üë3',1,'îİ[ã‚JPÚÍöp0ô'),
(1,'(«6,UDí¦`2ŒŞãk','–É­UîâE”…wlèÖ®x\r',1,':µ?6 N›¼Ğ†Õ(@d'),
(1,'(ØšúÇXI³êòÄÓØ','Œ7Ú›‘tK«ôŸ·oÈ',1,'ïd)cø+BÖ›KñàÀ¸{u'),
(1,')3ƒl‡6ON¶tB(bBß','xe^¹tGŸ¡üˆ±+ÍÇ',1,'”E¸è\nM™¢ş%0G_'),
(1,')«£ŒİG©”+E8¬m\np',',	Ö ü4E€ 5ºZ¨&ù',1,'†4ümê’D\Z¡ŠN¹İÆTm'),
(1,')Àµ9~ßM³²Üµx»ÒP(','¹1 ¦rG…˜ãTy˜ñÚ¦',1,'Jp9{ùG˜š}-P Gï'),
(1,'*J˜5ÉçG¬‹vSıÑëÀô','gr4ÃNò€Ç’®Ys=Ë',1,'\"ìŸÎ:¢B’¨íB;Ñ Çø'),
(1,'+\Zy`óÜAÙ·¶Æä•C¤','6˜Ø«H\r–QgÏM–Ï)',1,'Ô’®àG« \">¡²'),
(1,'+*Ü2wˆJ4!ë“P’','I:%ÏH\0GJ«÷°¬[¸ôg',1,'¨|ép*TNÑy³¯íú'),
(1,',ºÌ8HMh”\\Û#—{ö','‹›cüEAe²-Æ[Ö93ı',1,'düx­·ÈNÏ¸±6F,²ê'),
(1,',a\Zy±ÌC[µš³Ö: 	:','rğõİ.Ké·Ò®ÛıA',1,'£ï”¡uÀJ?¥B¸|?/Äï'),
(1,',ƒ^oDÕM¾õçš\\è','t¼¼6Ñ@ş·Ù1#`—n',1,'¶ä7nAt¿¬|Î½Ë'),
(1,'-X„p“ÜI\n¹rŒ3”$Z','__ëYÓ]AB™^˜Ñºhöƒ',1,'@Ó¿Hˆ²J#‰LC¢úÍ('),
(1,'.·­çï–LÍ¾Ëº´°±2','¨¨ı2âK)£–\Z`i',1,'İ»o„ÕIIb¬¤¥$-ÜÛ'),
(1,'.ÀVÜ\ZJ/¤XP\0•-Ó','!aûVÎ@Bµ¬E&ƒè›',1,'¹*ü¬ÑBn»ğ!ËrŸ'),
(1,'/W;jo_Aƒœù•§(r','bŞxr\0fK¾…øÊ5q‹Ô',1,';o×\Z‰XK\0¡¨à\'Dmİ'),
(1,'/ør`“\'EE·èÕûnİ¬!','®Îºa\0—KÂ¤f\rEgZ	',1,'Mÿ$ÍôGç¹÷·³n»&Z'),
(1,'0bF»×»NñÕFó¬ªŞ','Š@®~.BÃ¶®1$—€™Æ',1,'´†ÀXoJó´IŞ-Ã'),
(1,'0•…øÕK´œ+>múp','â¯EçìpMA¯ÂD::|Şş',1,'üAíéTÜK„´H¾ç‹1Ö'),
(1,'0Ó\"=§yL-¦³ñ‘ ','šœôNIB= ,cX9õ',1,'½~õ<!A@ˆ@g=wó«'),
(1,'2Ó£\0]DlŒ¸Ï÷q','Û:JzkïFC²¹¹‰/',1,'Ğ§±—ºNÖŠ‚ÁkX‘'),
(1,'3~CƒJUŒ6G¥Àia','¸×©_¹E©Ça×ÙÂ<',1,'²°Ä½ID³F“PM1Ò'),
(1,'3ÔY8ª}F¶Šfª“Rç´9','>¢ä²CŒ±µÃ„Îâ',1,'¼(•!÷1O„ÀÕ‚Iµ…'),
(1,'3Ø\r„ºzF”wl£~àÿ','MH—M¨¥¥OW©ã',2,NULL),
(1,'3Ø\r„ºzF”wl£~àÿ','»Š…	#¿@ë§ºàhXV',1,NULL),
(1,'4{œ©ê.Lˆ¦Á~F)âÅ',' Ë»±B¬¤÷E”ø\'1',1,'ÓO¸@±±ëÉ¨zgş'),
(1,'4°á®3xCÆ²Ï>i°Í–','8û¥{ÑE®Œˆì€„á',1,'f:ÇúªYJ[€¸šš<Ğtˆ'),
(1,'5l]ÉTROd¡sCG’°6','«ğÏ°SO£’Ìa\"ş',1,'øo’á¡DŒkráğ\0ş'),
(1,'6ÚÖAâëL^@fi‹dá','*\rÂB©C@ƒ»F–ï»#¬',1,'ŒleóCOŞ ¸0u\"¥¬'),
(1,'8/§€gK¼7üåæœ°',')Ü`×5Ai¿|ë²b',1,'ïQş>\0G›qşôğñ['),
(1,'8R#«”•A˜†M¢:Ã¸e','ÔçY/ĞGä•Xëÿ9',1,'³‘æîûßKãŠ‘”L‹jËw'),
(1,'9 ¦oŠDO²S½Ê/¯Ëæ','Ü=ì@6HiÔY¿îÛO',1,'ÿ¬ÅIE\\©5cä\'„´˜'),
(1,'9F~º–OÓ—ùo²>mÍ','ò7Ğ˜”N•~S±ÉšW',1,'ŸXL9K@Aƒ¬é‘ñ7œ'),
(1,';4è‹†ÆI¥?O]‚ûİ','#Îìz\r«Oš°a\"¹ÿèâ\Z',1,'uh»…ªJÒ­‰†,å±8H'),
(1,'<5	>ëpL¹ŠÇG½{¥','Ò(Ú#º L¼\ZPf¦Ÿ1}',1,'Ô’®àG« \">¡²'),
(1,'<¾çgËMæ©÷³µ’\"u×','¤:<¦GWº9pNÖ&€Í',1,'L½Cæ¨Nš™,?wLò\0O'),
(1,'<ë2û¡N ù3ÀµŸ','Mœâ¥\"E¼¡2(o',1,'BàÅG]dO|ƒ/ÇÙ ¬'),
(1,'=9–åMã¯¡XƒSÕ±h','ªkŠh@‹·J¬>û/ö',1,'—„¶@@œ°ÛÁ—j'),
(1,'=§\'OB•s—şGMô','í*x½WCF™ü¼ş)”',1,'T\nĞÿ¡NB€Ø£LôJ'),
(1,'=;ÃßD)–\\Ç»°…J','PD\n03G\"¼ÒŞ•ËÁ.6',1,'âÌnñÂJæ¡ê~Û9F€ '),
(1,'=ÀùL6•êŠâãe¬>','$\r¢uÇáO/£)¥Ó*',1,'ƒsÎ‰VîLq½ZœLµ¨'),
(1,'=â¶ ¾LI¡	oÔL.','¤3M°NCœŒF•nÃ¯\'Î',1,'ÍÇ#@lJQ²õ};<3'),
(1,'>Dr\"^ËF«¤zqNŸh','ªedÀ¾Aì¼ÿmßÉ',1,'ô»®wşD§§å‰ÕËĞb'),
(1,'>ì,¤”K¸¨ÕZÒÊ¼','¾Qº“|L—PÛa9—Ÿ',1,'üò¼(KÇ“AÁ]%÷•Y'),
(1,'?$#êH|˜1¨?®á','Š!u¿“SI&´1BbÙÕ;',1,'¾b¥œeFD?¬éGÂ`Uá'),
(1,'?D¿Ù*G’F\0ª8u','OY”ŞA3C>¤°hà’¡',1,'%†JOÏƒ3g˜Dâ8o'),
(1,'?á¦“ç“LÇ´|ƒ#©V9','¹¿+6ºıE&«Ä Ô·šE',1,'[äeêE[!©«ç0à'),
(1,'@†æw£E¡§\"qM]@4 ','\"Û†HRE	ª´Ô\0=g„‡',1,'lvÿ~Ió€Â¦\\T'),
(1,'AçpÀ”O%¿ˆ­ÿuöÁ\Z','Å*CN0PJÚÌ÷É|§',1,'8CÛ-{C*¨“u­…<«'),
(1,'B%±ô»Eè™HÓWğy','4ÆéÁZ-Kô»ÈTx˜ï',1,'gJà|GqI%²ˆgbA'),
(1,'B5;ŒÓ\\H.´\ZnùmføX','t\'ì‹tùF§úíN§×¥]',1,'(dâ\r‰@ “o&7.CÏ'),
(1,'BYK7\'ŞEhšOo³û«”','¼I‹†Iˆºv9´‚',1,'iòo8:¾M´’¸Ş?º'),
(1,'E@[ÓH‰‚\"iÛ†ƒ’','•LO¾ÚM3•ê–\r“ZÊ¢',1,'ŒAIBÙyG¿Š·p‚µĞ°'),
(1,'F+µwÿıL¸ºL¿O~É³','2*ÏOÇFB”x¹H£Yù•',1,'k¯‚~A\n¤¢v»k€ZŒ'),
(1,'FcãÚXNY¶t C­%','­ü=ÑÍEe´4Ìe$VIT',1,'ù\Z½ÖızHC–ìÛò5jß:'),
(1,'G_ÒÂUÙBÏ¡^Íz¡YÚõ','^®3ªjJ§^È÷€€â',1,'Ì0FµÈBaš÷ğ\Z—Æ'),
(1,'HÈ«¹ÔKÄ¦ËÿQÿ¦ğ0','FM#9ûy@K´}E.Í„',1,'‹üR‹õì@€‹-?”?N'),
(1,'HÜD‡îLç¨ ¡àÍÂÒ','¶-ŸCE¾tÜw¢~',1,'sŞ4êËE6¯2ÚDYp\"O'),
(1,'I	/ÿ¢.F­¼xö\r Ñwğ','>¿YÖÿ‹KŠ‡zÉc+7œ',1,'™ºøòOÍBª„£‹Z?Ş\\'),
(1,'IZ—÷¨ñNÙ•Şeé )À','#»D¦ºÖ],¤',1,'N>ú‹»C–•¿š¹™O'),
(1,'I•¦TğEªÅWóa!Ü’','—@,ËùDù‰««M4L',1,'ç÷(ÊÁãB¯Æ^á4r—ö'),
(1,'IíÅÆØDÅ¸n%c­ha','=së¦—éIÌš¡•@#-',1,'Æ{_ÂÙC\'¢9rA–æ'),
(1,'K\'ÁÀ„K‰=ñÓ4³','ŠSÍØIö•`#EÕ\"´',1,'wjjÚFÔş9ãkqÕ'),
(1,'LJ&™¿.N_µ[ÃQæÖ°ë','gäujşO7‹ê®A‡­^',1,'$¨¡dá¡L×´cB»#•,°'),
(1,'L |®@ƒâú8F@gÃ','¤pöVâJ4ï\n¶l…*',1,'îGÁ™U”DÃ€ì@Ÿ~µ'),
(1,'Mkc.\rïJl–®`é;“','™»Áœ@Všº’ÜõCs',1,'{Ø–R…{E-œp6Ji '),
(1,'Nƒ(Cá¿5›šqÀ¸ß','°­üYÌ!Kn™ÃS¼xÚ\rÇ',1,'€Ë	×6€J´Óù‚/i›u'),
(1,'NŠµ®Ò…B[¨l:ˆ+ê•','„Û2AmFò¿}zOå^',1,'@Æ˜\ršÁE‚„¦xß\0Åùˆ'),
(1,'OóàmÓèHÑ‰ø¼*9ŸÉ','tkmÕ«MÌ“ã%~Z†ò„',1,']ŸçqÑB™\ZºÜÎ3ÅW'),
(1,'PPUÉàA«xZ>½:\Z','IÓ»I0XDĞ«Âáã“¨¯',1,'ŠD­ëYJ¿µèöÊ$NŞQ'),
(1,'PÀõ-II±zÃ<y‰Í','’ñÄÍI@b‘;øÛ`9‰',1,'À\rSËCĞ@_¸İ‚‰@*='),
(1,'Qe¹¥A=š\"¤õ·U”','Í7ñ¼¾L­“	è ò',1,'¾<ÏéÆL=¢¹8¬—©É'),
(1,'Rçš\Z¶dHiŒ\0öÂPã','¨ƒ˜¶©¨J:^ìA¹Ÿ',1,'cÛ‘ŸwF{©D1 ï€'),
(1,'S/JÔcˆEV–¦É­{','}KòoéG¬Ÿ¸÷m} ŠU',1,'ºa•!O_˜:–Õ§ŸÙ'),
(1,'SSÊ—8>Iµó\"û5¤›','İÕœ8ÎvG§·VXÖ!Úí',1,'Ë×?šùH\nŠ—ü{­’e'),
(1,'TygG¼I°6€ÓÑX','¸\ZG6½Hã¨	JgPÂ',1,'Çw+Óü\"L-£Eü\0]'),
(1,'TÚDÇ6|JcºÕKë¥‘àg','üéC>šEÓÉ6³‚\\n',1,'ˆÃjb¢*L“‘ ¤W*Í1¼'),
(1,'Vj­îHV¾Ó9iiù','Lô7©O¢OM’Ä-îı6‚X',1,'#¥AíuND9ˆ„K=_dÈ'),
(1,'VšÃùĞÃ@¥«ÔÎ:ã','É¡št)’B–”$ı	’8',1,'V$–pHù„‰|Î\\['),
(1,'XQ² MAzª·£Wş„','«o1(B¥P&»—©E',1,'Èû—»_õCÖ¾£$>™×ä'),
(1,'XfîÍåH™¶8Ó@¹À”','3¼şi;TL€—XEı‚1',1,'Æ°Æ$òt@j®wÑ¬2¸¸'),
(1,'X¹/›ìFK3¥Ï0›ÔZK','	0óâ6%L“„Pü†¸:',1,'~?%ñ\rC“©îl]1ï®'),
(1,'Xş°æ=\nOÛ’ï/ĞbiÒ\'','»/êQF³R\\â`[Á~',1,'>Œã­ŠFù¢>BÀC×õW'),
(1,'YÛØ]6¨M½¹¥ÕóPö`','‡Ó\'nMüîû}ç8øË',1,'6i\n0ˆhCoº¥8Š9ò•'),
(1,'[÷	£¾G)òÂ#*œ ','t U:âfOà©[fÜà¾“',1,'\'«áb¡™Jò§&Í@ßp¤'),
(1,'\\yÓO)@4ªüG‚Ğ‡‡«','Å‚à­(ûC¦›Øiì*õ',1,'®\\‚N8»¬Vh=%¸'),
(1,'\\i Á ¹@\Zš? ¼\r¯†','\Z/Q –H½¬ uù‡ÂMÁ',1,'OªÙyëÄH–Ü‚Ñr|Ë‹'),
(1,'\\¶ñ5ÉA®²³DkÚÅ©¬',']zÛríùB<–/m–GÒ',1,'2*ü©Nbš™|¼-“à'),
(1,']d-ñ<9KÄ«µHºú«à','\nînÓİ­G¡§ÄÎé\\ä0',7,'ş¶³pµ%Bí”“¦Û1ˆe'),
(1,'^àƒ,sNø¹ÖóHQÏ','âj°*Mœœ±‡¬ÊT',1,'kt¦|·âJ8–³\nÕ´ûµÁ'),
(1,'__Ş“JK°½Oíå+','œëÄj°@H²ÑºÚûÆ7~',1,'ªÚY¨¹OÁ¶TÆ_fäg'),
(1,'_¿µJçHG´a”=ÓLù)','±&]WZĞAÙ”Mş?M¿°',1,'¬ô§3ôOÆ†\"=²ìîòc'),
(1,'_îuêqéFÚ†JÆS)\"‹Û','µ¨ú¬ƒOEŒ±)–Xö',1,'EAÒĞL?ò_â$3Hk'),
(1,'`	¤€êîB²†hsóÁÈ','07BèGØ‡Ğ4º{c',1,'ù´|%DÈ·æbô¤?!·'),
(1,'`¡p_8Gÿ«Ç~kÃZ/','ä9ñe@äD…±$í]‡ÉìÁ',1,'Ó aËQ$KWª-²40x'),
(1,'`FÉÓãÑN¿şøR^Ó','µÇª8Dw§ÿ,,¨ k',1,'K^£ä¡K2©_¨¶\\\"€¤'),
(1,'`½{]çGG®ê8^«€(”','<lQu@=´³·l)2²b',1,'¾bM:ßzAÅ–ZT÷2‹ö'),
(1,'`İ_AõB‘ÙëbŒgO±','e(èX~KJŒ©º€µÉò)',1,'éGªñ`kA¨Ğ¾[»™'),
(1,'`ú+µãÖH‹ÜXø{ş‰','‡>2¶0ÉN™¢€6Â',1,'<pó xÉIŸFÀ>MTóõ'),
(1,'a5ôï©ÎAS‹p™>É6$','Â*Íä°ÇLÊ…{ÔÚ=²‚',1,'Ûò™rCÈ˜#”İ`ùå'),
(1,'a˜›4ÿN¦«ıˆ¶ÿM','wº®-Húš2À´ˆu£Ô',1,'RF.ÎÆ·Hç³ê`ÃSà'),
(1,'aÀ6\\3AX«-Ü;¦êx','Íë’ÒÙK¬µÕ0d`í',1,'¬3’Pz)GI’µÄ8À©'),
(1,'b‰£= ÁAø‡ôŞ=6¸_','Kä:.‹N¨œvHŒö†',1,'9¡óâàO’ŞÒl¢L5F'),
(1,'c?\0¾K HÕ4ˆêÆ','\ZÀdû‹AFhÂ¨	}wÁ',1,NULL),
(1,'c?\0¾K HÕ4ˆêÆ','G$£è†GC™D©$’',2,NULL),
(1,'cW°¡ùÂNç©‡ù_-é','štyú6Bw‚]ÊhîHÄĞ',1,'\nÖb£›C÷¦DJ•À—1'),
(1,'c‘FtVJ‰Ã@s‰','şg›ùO`µ+âµpU`û',1,'“¸¶SnDô¿ûw†¨zÁ'),
(1,'cŸBğsEâ‚x®Ó»æ2û','êÛ¯´îFo’Gİë¨',1,'IT°ïy\'B-¬ã&\rÄùò'),
(1,'d\\\ZG@í’	‘š\"€~','U—ü>JO³•=ÎŸY',1,'=SÄÑuA­[d– ˆ'),
(1,'e¶\\^¹GÒµ`t}k4™','§!¸¬4OM#LÏµ3r',1,'=,i!\ZF ö¶’ï:IÇ'),
(1,'fRšÎoJÜ©\n?lÑA','ÒãEn¾Ac»—<³6ˆÕ*',1,'NÃQ?6F\n¯Õ­¹nî÷€'),
(1,'fSip¸I%»db','h„~iIh±%Ÿç74eÙ',1,'<òÁà#B³’Ğ$·ã'),
(1,'gj#Ó\'vMƒ™j}sİ£0','Šsu pE\\¶Á„%Q1E',1,'d.muB‹6º@%½\\G'),
(1,'gxí½C{I §±ˆw ','„5ıö„E¶¥T_¸Š™+f',1,'&ÅDÛHÓŠ\'\næÓ+c'),
(1,'g}.À«Dï„ĞÛ\"ã¢%','?î‰ûïNo£”ŠÒ$åÎ',1,'\'Y¼²¨K>5ğ÷®ĞH5'),
(1,'hƒV JÄºÊ§›×','m>p*b@¸Räû½ıÀ',1,'æ“Ò\"HIª—F¹p+¨vŞ'),
(1,'i¸|oî@é‡væ+ª5','jî8¤İwEZ¸É±#°±N„',1,'ĞÄ#5L^NW‰v§*V¯¡'),
(1,'k`¶¢ÕD‚.úùƒ@U','÷ıcÄ.C¶àã6Ã= ',1,'8|s¹8@Ù‚Dú‡vÙt'),
(1,'k™Ş%r÷G+õ@¡©x','½?,œpLO´´}&Ê1¾‰',1,'\\qX;F…ˆ5a”`Äf'),
(1,'l:°ªt¬NİªO3òX”‚Y','ŸGj	MÙ él»ú,\r',1,'»\rPÛAT´³<õµñ'),
(1,'n,ñzëíB#«`ı=¤','‡SÜ°I:‹Ôun¾ö\"',1,'SÎèI9+H5‘yÖJ@^©ƒ'),
(1,'oˆ=¨{êJ¿‘9tÈok™à','EŞVõE»k5ìœ–Ã',1,'Rò$Šl×Cµ¹§EgµÌ'),
(1,'pKº÷ŞDø­Ø¡ÚŞI','t:tZËA‹¤\Z.Ëv#õ',1,'!U52Û}G”¯½°Êvæ'),
(1,'q\\¹oK­h\rí»Øz','P:-O¡ÆFf»9h÷',1,'Ü‘®Ñ‹<D£¤Àõ>x8Õ'),
(1,'qV«(øN—ŸK[c–*~','œÔ\0­JEF·tœ3m³ò',1,'Dî,şOì«S­á3½İf'),
(1,'rÙ€F¬ øÖˆ¾ã','ÎJï(ÿG[©¶¬½À3Mú',1,'p\r¢a¢€Iy”˜4-Şz6'),
(1,'rêŞhB¢¯ÁÃ¡ L','¿óÕMF	²7„BÌSú',1,'®å7KàNèK¥AØã'),
(1,'s@t¶\"F—³àv¹—G','1ºYşO²Hb\'B}',1,'‰\nŠCÔÇX€V®Î'),
(1,'t çO;kMH¯œê<ìæ†','7)Q?ëHÂŒu ÀU¶Š',1,'\nğŠM²\'@ô’Bw49#'),
(1,'t@ıDÈäIª€Ø|Ë','Ö ù\niK]¾—\"©¡Ä',1,'´ÅùY½L(¸¸•N›ïs,'),
(1,'uI‰×,D?‹†İL/¼H','mªKHDA«åXw~Ql¸',1,'­‹ÇÒvMü©`,Ó™ıÂ'),
(1,'uËèÊåNò¹-([È\0›o','rş¥„ÿ@F›¾$(	zi/',1,'\0æÓÙ¨B#J=Ê;J'),
(1,'v5½\rGÜ„öâeø','K™…ü¬aI†*Ûb$M¯',1,'ó”\n{º€BX´\Z[h+dú'),
(1,'vG·TVMD…?ÓóåÇ”','X!oA3Ldmh\"üt¸Û',1,'“»óÀB1\\ÕüdbÙ'),
(1,'v’×Ù,@»ºre1‰^O','ª¬HõpNİ¶uæãZ}]~',1,'JüıAL9­æ\Zq8¿èÔ'),
(1,'y…à°Ds¬°Æ¸»×À','Óòd[Dàòï.‚',1,'>§K\"tNu‹‘5bUûò@'),
(1,'{¢$ö0ÄIè‡ú!Ğ\"È\'','ÓeÓ¸£ÿMÛ¸Ú¸z–ä',1,'ŠÏ,ş 8O”¾ÌCç÷¢¬='),
(1,'}t8ÆÿğLª0pQXvgD','âG 4‡Ú@f€Á?õ0íO',1,'9ÉphàND­0ëbÉÖĞ'),
(1,'}£ÀÅ,B7šl\\Ì‚ÅX-','è§z”$ÃIùc<‡»y',1,'„ì»õxMr¿ïvøñBy'),
(1,'©5ŸÇKˆ·¸ÌÉõ€ş„','¥!^Õ©yFƒ‰§–63Ÿ',1,'\0\ZäÅyD\\´~Lc°‚'),
(1,'€7ÇáòvJÕ„g½šD','AÿÃ+4ëB‘€” œ*‡o',1,'ª¤†‡)Dd™œ$Ÿ—øè@'),
(1,'€—¢`cM\0»©½8Km','FG½÷á@—°¯Ga–&O:',1,'§ÊÚ×iE|¿LaŸè¢Ö'),
(1,'ŞC»®c)Ä]ƒÓ','÷Ñë-¢\'Lªíh6İ²',1,'\\°¢P‰E\Z€¡-G4Á<u'),
(1,'¥ø‘ëÏOúl¢ÎÂ‚','u;QóàcN>ˆ¯{´$MëF',1,'íÚ7gGH¾‡\nÛ¾C'),
(1,'„ï%±á’F¹}‰3¶z#','°$ËDaMı§ÂÃ^-§i',1,'µoJD#\'‡OÍ™5ÿ'),
(1,'…B¢™\\	A2´Å¦‰×>ı','å>`¿–Dš°0à1¡Óp',1,'…HÇMõ¯QYŒwò‚'),
(1,'…\rKÀ¼‰Çütãl','ot)¾eJ¹—Ìås¤…+',1,'BCd*+M£´-ŞŠË\r©'),
(1,'…å¼jiãOÁ„ı,¦Ñã','‹¶\'uCÏCVœË',3,'Å+¬|<cA-ˆAâ§Ìä9'),
(1,'†!k‘«H×ˆã!DÂEo','£JŠÊŸÃMŞ«xÜDZî',1,']­à×­I0¯í§üA&'),
(1,'†à?4IÌ˜ =®*pt','£N[’†ĞL‚²?uò4ı',1,'™éÓĞ¡LEv½/­8ê–f®'),
(1,'‡r±Ÿ.oDïƒ§ªXpşÎx','&£å‰M«ôßÙCD¹ì',1,'p;3Â³3Ix¿uÂòSï'),
(1,'‡â™9kåIÀ»5õC…ÂÖ™','Ó-œxOG…º\0çí¤ò',1,'pFü;AÊŠ\'·,ÃÍp'),
(1,'ˆ%[u»G ºØ¢V','Q\Zˆ˜ÂÒN¤·väÿO’T­',1,' •,õŸiM#—R8S[„~'),
(1,'ˆ­ä˜Y‡K!¦s`î=6¡o','>Ï0á\ZD“;·¿%éİÛ',1,'yV#ÂòFF¸²@r_ÊÑ€X'),
(1,'‰1rà\n(Mş­6ëíï¶óÃ','2Fq^L¢«+m>‡‘\0è',1,'‹€WÏKG¬™MP1ïËÀ×'),
(1,'‰ÜâN¶Mêªéèêİô9T','è™Ù2IÖ‘€x8+R',1,'^o‰RM÷•z`_¹‡'),
(1,'‹!r°/NqŠ3Ä:æN','€Ó[ñB¯˜A{sÊn¥',1,'™ÜrÑ)L¶¤QÛé\'ì'),
(1,'‹IÎÈ¯JÅ“æ¿ÇR2eË','HôÃ’ç#@‹Ñr]oåú',1,'ÚÊH`y›Lä¹z'),
(1,'ÌÛä—N„‡!bw^','ğk0¹çÊHç–2&bÊ}~%',1,'ª.òÔA‡uÉFèXñ'),
(1,'à¯IÇúIËÍªºµS…Á','(õüù¨qFF¡ I¿ÎÕ4',1,'4ß}(GÏ—æuæt#ô¼'),
(1,'õ÷¤t½O£ø7nã”ê','Ø°#P¿FJSÓ*°•·',1,'tÀpd4NÙ—»íë8ƒşõ'),
(1,'È¢» Fšf·r\'“\".','•ôPX*ÖLo™S¹\0Õk}y',1,'ck\'Á‚KE!”ÂT tO'),
(1,'jJÆO,¨%­^(¡®','ŸÇuã²Jœ¯À\r©cù@½',1,'¸XÕ2ïA,ŠaíŠÌõHÈ'),
(1,'dáíO%Oç•º%èŸY—Ê','„Yò¸ Kå¡Ö.ûy_è',1,'y~q•ÜúN³•\'4R—Ä'),
(1,'§Ø\Z–Ä@)šÂ Có¦Nì','Ã¨Tâğ‚O‡µpõ<r¨§ù',1,'$´§CÅC˜’<ízË#'),
(1,'åÊíÁNµ‚õQ``K*s','İµûĞ–H¤èÁ—-ö ',1,'úOŸ\\/\\L™»k>«F¦'),
(1,'SJí•%Aş›z&È…Îß¼','/lÜA©òGKaó•¨›ó',1,'|ŠÿÒÎKŒ¸Í+D-1+'),
(1,'”6\'fF’•5ˆÖÓÕr©','•Â?\\µ\0KöŠhTõdfÛ',1,'³\\rtóDF±[]Z/S€Ğ'),
(1,'•<­ŸhOËºnª		`','ú6àÿA]H}•dÜ”oŒ',1,'ÃÄ!KWÇJEœxÆDçÙ0?'),
(1,'–`zP»Eò¯·n§QÌù`',' K»!Ü(B3¸Q®Ç”¿¥‰',1,'pëÍYÊ`O’Åi8ö'),
(1,'–À¡î¡•EIóÁÑ Mvh','Owz\'ãUM@Ÿ& i€Ì\"',1,'ÃÍÏµs]Lœ¹zô(¥´ÿ~'),
(1,'–ÊxO¥Gêfp6³','Íµˆ àiA„¯Ç’5öÂn',1,'èêcİÑ6C²±C¶Åõ'),
(1,'–â4ÄÿH±’®¹pİk#x','÷Gíè¦Dg˜Ê&|}èè',1,'vÇt_ébJ„·çŠ%Êå'),
(1,'—»RéE­–ãŒ€°','J‚­†Ep©×k	azŠ@',1,'ó—äM\'¿òa-nªÚ'),
(1,'—l«²/G¢³ÙE5nA','2³|E§¹hœb',8,NULL),
(1,'—l«²/G¢³ÙE5nA','ğßk„`G#©à¬mTÍ3',1,NULL),
(1,'—l«²/G¢³ÙE5nA',' `\Zœ`AŸŸcd_jÊ',4,NULL),
(1,'—l«²/G¢³ÙE5nA','\"]øui­D‹——ûÓÂ',12,NULL),
(1,'—l«²/G¢³ÙE5nA','>•U¹«˜L]¹ÒM(eMa]',14,NULL),
(1,'—l«²/G¢³ÙE5nA','D„Ÿ6´–Fj§æ¦ ÈË÷',7,NULL),
(1,'—l«²/G¢³ÙE5nA','RV€?XK6¬àlc>¹',10,NULL),
(1,'—l«²/G¢³ÙE5nA','RgÌ¡ÏM‹]¸_÷P',6,NULL),
(1,'—l«²/G¢³ÙE5nA','S›ìß÷˜A•¶ø$„/ÿ‰',5,NULL),
(1,'—l«²/G¢³ÙE5nA','€nhŠKB–ªqJ?YY',2,NULL),
(1,'—l«²/G¢³ÙE5nA','«B¤™û\nCP­\0;AÙ)_®',9,NULL),
(1,'—l«²/G¢³ÙE5nA','¹T“I>vKÛŒúD]İ&',13,NULL),
(1,'—l«²/G¢³ÙE5nA','æƒı€£9GÃ†¤Ü_ÄÇÆ®',11,NULL),
(1,'—l«²/G¢³ÙE5nA','÷°ö×B@C-›1µ‡½üo',3,NULL),
(1,'™`æ÷2I‘¾;)Ár¤','ÊgŠˆH»ªü›’şÌe;',1,'³ü§ŸrºHíº~2_@“'),
(1,'™…Ğï=&A‚E©sôŒT','+ã“ûL›¿KYJ£{Ë–',1,'1ÎZc«?CÓ¶Ï{âY¢Ï'),
(1,'š+[dí¤@†­Ll°3d9','®D\0l_KT›;´4\'©Ëş',1,'ïvª\"Ú€@ñ¼˜jtÖqi}'),
(1,'šrçïì¬B.œ˜³{¹–(','|;\r®—øE¸¤H,\n§a',1,'àòAÉæBĞÛº¶ß†a'),
(1,'šÍÌú\"¡LL‡ş9l\r','¬hÅ®>Mè¼÷†\\Ûõ9õ',1,'ï_İ^ê~Oƒ”E(v¥÷'),
(1,'›K!ì!4A¿¥`Ü‘hç','BÓ®ŒùF=‚£P(kv',1,'w‰¯„*HÜŠˆ€íä°'),
(1,'œ6g¾ÃB£^aÈ¯§c','J›Ùù£xH\n‚÷fµ¼r5©',1,'°]„W%ÂEQ“¦cùwô«Æ'),
(1,'œ…H«I|K4)³‰e0¤','sPÉåxJ»¥	J„á',1,'ê\0GDMğµeãQ¥:-“'),
(1,'œ–†ÏsBÌ¹d[ónµñ‚','˜ûL»-áO”æ£­~e¦',1,'qˆÒk6LS¥Oÿ°ˆkZy'),
(1,'pg×ò7GÏšOÅÈV­ç','²\0Ÿ÷J—©;øø4¸±a',1,'Ïsù¿_…A)ƒ¶¯²FRR'),
(1,'ä÷q57Fw úËV®\'6','ù¸“F\ZIyö=fKäÖA',1,'Óff±I‹òÌĞëô ”'),
(1,'¡¤w“:|OX†ØZe(÷','Öµš?”IB!»=ŞœêÂ„',1,'è&]«®@IƒC„»¯}k>'),
(1,'¢Åx­BOœ¸H€˜aÿd','*­Ãƒ†Må¡ÛøŞ6tG',1,'òzXÑ©æBbºë$¡ô’®'),
(1,'¢ü³³©/D×ˆÿïKt‘À','ÜVÇÖ nM‚Ïs{ËLL',1,']á—ò^@a–š­4	\0İc'),
(1,'£?ôøØåKê¸\n=h~]Å','VHDz‹O¤‡t«ÌÅ¹¬',1,'UaòdA]¥Âàúlş	'),
(1,'£ÔmM¸If‚ÇÆ†´Ë$>','7À,‚ZŸCI«öIE‹¹M',2,NULL),
(1,'£ÔmM¸If‚ÇÆ†´Ë$>','M\'ç¶AÚº	™|§½Bè',4,NULL),
(1,'£ÔmM¸If‚ÇÆ†´Ë$>','[@¯Ş¢×G¦«”$q+',3,NULL),
(1,'£ÔmM¸If‚ÇÆ†´Ë$>','ÎMY¡šO:‰@«	æû”',1,NULL),
(1,'¦[¸\nOßÉCFñ=ï',':v+§ëiLH¾\nØIé€<ë',1,'É³jb4³M:‡İØõ$'),
(1,'¦ÔJêVFf…`\nÑœã†©','@KËÍWƒO«”†ˆ¡ÌŞá¶',1,'ˆîÃWrDÌ¼:ıœrÖÃ'),
(1,'§I.éå’G;¤›ĞËY@','¨Î>öDÜ«R\'cI–S•',1,'w±¢\0\0şB¢‹ÓŞ\'%Yş½'),
(1,'§ŞÅ‹…Nòƒõ[8I9ò','!å&º“õEÆ¸ÈŸ\\¢Úİm',1,'üBˆOƒM$¡_R\"pÛi'),
(1,'¨(3­w°FÛ¡³qÜiÒ²˜','ÑÁ__“¥JÌ¤ >}Æ(›$',1,'æ~ŒšZÜAo±ãî“\'|˜'),
(1,'¨q¤-p\"J¾»²Û²Öû','efòÙ•Kb¿w2‹5©Ï',1,'1¤¥5ÚEë—FK¾s·’'),
(1,'¨a•0ÀJ|•œø^9p…ë','ûë‡¬šCæ‰T.¤ñYÊ',1,'bzxLC{F®ƒ,K&ßrği'),
(1,'¨°R ¨•IŒšØa:­&K','?´îØ@z‹!ç¹7jL/',1,'CM’NèwI=•G¥K–¸#'),
(1,'©Ÿ3[úm@t®Rc^x\0ÁE','Ú¡0Ê„:H\rƒ@Fª³3¬÷',1,'‹gU÷ùJt—ãh¼$'),
(1,'ªÀ³5iıK^°â¬7Amã','’ØÒú-E,“ Ğ{l¶½',1,'ÑÓš¾$N•m|™Ã>¤'),
(1,'¬$ĞÒªOc£\rÍ,#Ãíı','Ä7ú»ÿúDò¨ÕÅb!Ønt',1,'Î	SHt¨¿GÖ²ç'),
(1,'¬oa’ú®HI¨ş˜ƒ$ıâÏ','Háiò>D¿˜Ÿ¹°úğ$X',1,'=¼%QuEâá.yÎU'),
(1,'¬Ñ´\'•KÀ…¶@öC}ˆ','=d]®K—ŒÉ?ÀlkH',1,'òï­A~ÕK±…‚­ürÄ'),
(1,'­»hOš‹Gz¯½ßºÎ\0ê','”#8‹ôHÏ´®Ê_35P&',1,'Á°¸J}BC•ò(¨ò\"'),
(1,'®*ÿ.‡ØNúš¯Õg^Ë%~','”ZÂå%I@¢”\rMNÿ',1,'İ/=®@jºñ,õŞÑ'),
(1,'¯,»LyLxší:Ğ©oò{','@HrBîO\'©¿–Ï¶Á',1,'®Û]H\r Jı½µ›\'X;ÙÏ'),
(1,'°ŒöåN	‰FÃÚ?t3',' 	ë[4CìÖ[½„ò$',1,'|	^\nS8@K•Ã§ ¬|*'),
(1,'±–}[8K8Ÿf3ìÅ','MÀù÷ÙîHÆ˜bŞ	A~Û<',1,'¢)²VBKı¨ÃnñaL2'),
(1,'±ÛLTM¨¶ïŒô ¼(','zõ‚´óÇ@†²ãÓ}kØ',1,'zéÍDä«ÌPVıÆ¢'),
(1,'±ªÈå2rJW”¹@Ó?cÍ','GšhR&F\"œÔVÖ*jó',1,'³	wr×K@ô\Zü?AD'),
(1,'²^Å©ñIb’Õc8,»','8±= Z³KG–®!ã*†',1,'ÔƒÉ(jLk¨Ï/W†r|='),
(1,'³YpóL?˜™7•x`B ','¹j×+OŒ…™´NÊ~		',1,'Œ†‘×\Z9Bõ€$QvĞ½®)'),
(1,'³§oı‚+N/‰*ìŸºŸÇ','ù,ü‡rÌDü“7Íõ¼†',1,'´qä2æAu¿’Ù“	¬•'),
(1,'³ş´VppH‹ƒ‰å/´tJ',']Æ4-UF¤Ÿ–­¤ÈëQ_',1,'‘ÙB\Z¶‰@»¡¬%NpÛø'),
(1,'´84$‹¬Eí”äÜ‘a_#','!ìß\ZMÛÚÇÆşÌÆ',1,'£3\rËE‡‡[gÍ§3w'),
(1,'´\\ö7ŸNg–¤ÔÙààÒ','æÕ§CbtHb³…ÄNcÕÏL',1,'=Ã EïN¬•\rJ½ ß'),
(1,'µçÄ!á#MR²z@¿ÅL','·3#(7¿FD¼	Rq™¦',1,'yhj\'d°MAˆÃF†.¾;'),
(1,'¶Øb+qKcŒêF¿Cªë','$õu°D®‡¯[ïÌGË',1,'½=T÷¥Jû°â_µµ\0B'),
(1,'·ìá¼\ZC–ƒ¯u\\$~','2ë†Î9ğO5¦ºşĞ^qX',1,'ïjq)ëƒD£–ÜàğDœ'),
(1,'¸{T¨@H÷“Şé’Œm7^','ªÓ\"Æ¶Dr»á sæB§',1,'}ŠKÙhGD*°Ä`şÒJƒ'),
(1,'¹¶jºFS©^ÅJ\"?8)','	€$nZhKt•EÀ!Ëš',1,'*÷âäãB]©Ç·8¯ÛÛã'),
(1,'¹é†<œpK‹¿ÚÉá·	','yÃ*NK¼ŒåœK¡uµ',1,'ìwÔ)`øHy¢UÛˆÔDªK'),
(1,'¹õıB1C(›Sc¶+{','A~¹§8&C©ø’–¦Éõ',1,'5P–ŸÏMÂ©p°2tûtÚ'),
(1,'º–Ø?KN³Èø:õÁ','§õø77ÊK¯µ$´Ö–ˆßŞ',1,'Zå´ÕÜM„–öåh±Í÷+'),
(1,'»*J?ƒhEí‹a%nŞ‹íd','È*e·&CB9³÷«¯|h',1,'xùœ¼^Cª	÷‹ŸV'),
(1,'»ug@Hv¹ÿ[aÃ&Ì','Õ`=üWM`œííº°«1D',1,']:‹#ABO¹vÉ…s=Fo'),
(1,'»â¸á·±B7·ê9:¸:¤K',';wã}%ÚM®™÷-Œ8e¿Û',1,'—Â›×2vIc‹†%FNéÎ'),
(1,'¼D¾~ôA@£|Í®Êì4ò','\r¢sšáHt¥õØò\rR=>',1,'Õ„7_y@b²úÇ…î\Ztõ'),
(1,'¾ÎÂ˜÷hF>§‹Á•.ZØ<','ãˆhbNAÔƒÃQ_i/ƒ',1,'Ë£NôACƒVRù*'),
(1,'¾óŠ?rÓK®à½RV)1©','\0²ÍrİGS­xs›OÁö',1,'‹ÿ5s\'Fµ’ÕJşC u'),
(1,'¿Ñ>¹IÏ¥°”Æ5','»aİ»Oè²A_YùNe',1,'¦ú+“:I6¯X%,kv#'),
(1,'¿àÉnƒ8B\n‹-Ÿ¡¥v2.','„¡İL@’!çE÷g#',1,'=µB5ànNL¥~HÜ‘‹•‡'),
(1,'¿ê¾ü%^D‡£™·[ø_eÑ','*PgL­\\FÕ!pÇ',1,'1s*r#ÁN¨¶—{ü­q '),
(1,'Áhm\0VXDš¼TåÏñJ','‚[Ò„ßWJÖŒwAí¿F	‘',1,'Ôaj„7MWœûì„D.…s'),
(1,'ÃÑİÓ5@ß©|¥ÍCnı','tNëÂè˜B¸ík$¦ï^›',1,'Ğ,¥ƒ§L œúª»/=ªa'),
(1,'ÄtË²-JG–×¿•&Ò4','€YĞJ¬Jê«®¿Àüà˜',1,'#ŞIJ<ÊA\nˆ‚@Áìa7'),
(1,'Ä‘e·æH0ŸñxegÈwÁ',',p‰¼½èAN‹Û¾á/rt',1,'/ÚŒG¤ŸNÿ¹jwÜØ‰'),
(1,'Å7ÑPpA&»OHOˆ­','Áşª<p*B½¢Ş\Z$Ì’',1,'‰M‚UØáG*ºÒ¼o7Œ‰'),
(1,'Åe[g,9GœºuÒ¿M·q','Û\n¦4ÕíAğ§=¹8ï8',1,'Ù´^ÛS*AÎ‚\0Wœ¥­ì!'),
(1,'Åë8:_DI YGş“f','4STãyJµ³âÆÓ°Şó\r',1,'¤ƒÆdM\Z‡l|gñö'),
(1,'Æ°«Í´ÑOD©úñ3Kdöd','s`&\\ÒN·Œ_µğ_àƒí',1,'îkF™?)N/Šk‹_\"™Ñ'),
(1,'Çñ&8ş˜BÌæÍ·>#œ','Ô ÎƒøC!Š¸,œ<©…',1,'ºdÈjZ,FúŒ3$ÙWÈ'),
(1,'ÈÖ³‚ô)Eõ¿fy[¦€','cŞ¤]3Jâ¶—_»nw',1,'ØÁF@­wN¯t\"Ú\'ô'),
(1,'Ë}™ŞÕCÇ™Ï\ZM','*ÕMII»¹Åkk5.¡',1,'ßQª¾ƒ@æ„\ZœGèáù'),
(1,'Ë}ÃMšŸH˜©h\'ÁÔ<.À','£vS	‹AÅŒ´nHÿYÎ',1,'’i‹“@‚™GÉÌAd<'),
(1,'ÌèÉV–D\rŠ5R›\'ë','JÉ§³ÁÁGC½29#Yzµ/',1,'ß§}gì*M1›úÇ=uNq—'),
(1,'Ì”ÃÏ<¹JŸ®zHr%0Š','q­DäJMI¤UG“ÏNJ',1,'ØGÚöMO–3,Õª~!\Z'),
(1,'ÌÛdgÏI@\Z xxåmZğ','\\˜Ü] ²CÈºŠÜ­á',1,'­NMÅ¹{ÍËî©÷'),
(1,'Í.ÃŒÎÚJ?ƒYúØÌ²…î','+©!ºLoNoº¨C/Ï',1,'éH‹¬|vC¶€sXÂõªÀ'),
(1,'Í­2»ºN §Rš\ZyÃ”','ö2>kBa—‰Vz«å1',1,'pYA\n—òB-šˆôôÆzß,'),
(1,'Íš‘ÜçSD\'Æ:sûÃzÖ','ˆ»¤b­ÔCá˜ZÒ[¢ƒc',1,'SºòM+¼ED4Ğ'),
(1,'Î¨o¨O[°ß^9û[»§','„Ò²ÌòF@J‡µT)\\cÚÕ',1,'t.×\\¶ŠMk¯óÚ·l/¥Y'),
(1,'ÎfÔp?@Ñ¸à‰YG¡','ó’˜Ç×‘B_‹ä§¡ö',1,'#ÜwxÖJ´®©h\0ş¨)'),
(1,'Ğ‚\"FõM,ŸÎİæ…¿*p','³î0š™A2¤CvP¾Ë /',1,'0ğbPÑıF›†27IBÒ­'),
(1,'ĞÀ¯4À¶AÉ‹?ÀÑ©\r','û—Ãµ6K™Ì˜^”}T',1,'ëÀŠs9ACÍ‡Í7ZÆ0*ø'),
(1,'ĞŞì?ŸrKÃ®×D„øÜØL','œøa*ëYM«.t®å§',1,'´èÙp	YGçµò¨I'),
(1,'ÑªÜ9¡Ml‚,Jeå/Ã','ØhÊ¨ßFt€,ç+G',1,'Ú\"…„è®Ií\\P¥şÄÔ'),
(1,'Ñã)(\ZJº˜îÓ6¯\0','AÁ€ã¨N§ÊF‹_9œ',1,'×2ŒæûåH,F9\"Š\"Ú,'),
(1,'ÒócÎ®äI­²³#y˜h\nï','S¾À¤áôC©¢²[A<ç',1,'-B?h’J‡µ³Ä|:'),
(1,'Ó,rWîOrº¿Äëß£š','1ïÒ0nˆHœ\0`R!¨j',1,'céËA¯cê¾~ÀÙ'),
(1,'ÓJûvEaƒõ‚ÅI\'','â^\0U$F‘‹9fèrM¹¡',1,'J®ÀëòBà‡ìqƒx=É*'),
(1,'ÓÏ¼iá“Mµ³[	²„Şk','ú„ÁqñÃJµn[b¡¦ï',1,'Å&š¬båA ‰.rÄ‰9;'),
(1,'ÓàíäcêNB†\"!ÖŠÿ','<,Ôb\nOæ©H-²É¼',1,'p\Z¿=›M¦•g:ã¢®_\r'),
(1,'ÕGé?G¹š•\nßdîY5','N\rİN{‘Å˜Ã¹âŒ',1,'X,ô¦ŠDK•%m 0Jë'),
(1,'Õ¥ *iéL\Z¬Ñœ1õV','Î¼S! L—¸šåB\'ì',1,'å/öŠC&¿GÊRÆñ'),
(1,'Öµ%¿.K¡”6ß®L','Ù°¤2”ò@¡«)aµ…øU',1,'Ìƒn%ï–H±²†|A4—'),
(1,'×`Ä¢J“°’\"Vş@_','Ùa…p,kNPBäH@–¦',1,'¬À!Îù¤Dº/Ìubô6'),
(1,'×Ì~½°ÈIä£Á¨P(\"£Ö','ëõ`‚\\@©¤=/¾¹4',1,'xùœ¼^Cª	÷‹ŸV'),
(1,'×öph˜K‘a­ÆI¿å©',':Uå g÷M¬”HÛšœ‘¶t',1,NULL),
(1,'×öph˜K‘a­ÆI¿å©','™Å>ì\'pKç‘ØìEò‡ä',2,NULL),
(1,'Ø¨wõL+B0ƒyÒ¯=¦,','¸»›Á©GŒLgÓ0¸S',1,'4¯m~?êLü¦\nø¼¹ ±}'),
(1,'Ù·d 2O®é¾¢ÄŞğ','ÆR\0åşEW‰7±\ZÜÆFø',1,'\\¨I}µxOyµú°»X ú'),
(1,'ÙÒ5£sIí²0™ù¿Ro','RÀ—ü—DŠ?»©åB?',1,'ü\r+$GL¯Î+kêÔ9'),
(1,'ÚEÚ¤v†B§ŒgÍÄ\0ğt(','\rŠWTL3‰™o>\"T.',1,'úbdï€{@É¿KÉ&'),
(1,'ÛSG”¿Ç£Iƒ…Ù','´HÏ2GŞMI”d¿²',1,'äf$ëûE-™Ó_v›mxx'),
(1,'Ûg›Î”çD#¬÷öÙ[\'v','\\k‘½Z­G„ŠX)-æ~C',1,'ç_´òZI§H&¶§.‰G'),
(1,'Û›Kì;•BÆ€ù#Ãò”','pZ‹½ö‰N¥’\\‡’x¸',1,'ú©¾OĞ‰KÅ®ƒøO@’e'),
(1,'Û›M¡XM¯ÀÃBê','fEĞVEXJ%†vÒ°·Ş† ',1,'%JşND]®aÉd¸å]'),
(1,'ÛğÊ­¹eF™œLI<«£€é','ënù:õMŸ¤ê%êÚ†',1,'/Ô®R£Hï”tùÀ¶)'),
(1,'İ#ĞJiHv©×Ï\"~v','ƒ§[…Iª»äeĞ!ç',1,'pï20N«±‡%“&î·'),
(1,'İ(j£IIˆ¯¤÷º>øs','`’ÑĞLÄ‡³ámö§&™',1,' \rAÚQİ@¥±d3öC’Gš'),
(1,'İyZ¯ußBŞŒ2\r%êå','5k<LB¡“ˆ‰m8',1,'\'QQ¥å)C+èòÉ—mö'),
(1,'Ş<z—QaAğ¤!)ä0í=1','k¢\'9î-LZ•œÓƒ¶Y,',1,'à”\rY@šIAÀ0'),
(1,'ß+ígÌuK£¹Ìê3ØlÙ','iÌ1ÔwYD`†Í13–÷Ù',1,'Š9è`±ìG»ÅÉã¼‹Œ'),
(1,'ßX•‡0™NøŸ” Y©5','Oİàu‡H<›´\ZNëv±',4,'‹kœíyjMp¡ò‘¥J8PQ'),
(1,'ßAĞ@õ§œ—v÷!‚','Ö*¨€73Fö¨Õ‰ÚÎã ',1,'&Ø³ªJ; `Õæ.'),
(1,'à¹g›L)M³Óºåşß','M¸÷†O‹¼l÷ ´ i',1,'3-ñxÃC:ºõ¶Á,0Ë'),
(1,'àîÕÿCBES¯OvGü0Ò','×3$ó\'çM€•¦õ]‘',1,' \"#æ²\nDµ‹Ò0c+·‡'),
(1,'â¯¾ĞFYˆÍää§’\r','U>…üC†·Ÿğbò',1,'ëÛË4ÙF¡,şÛƒ!='),
(1,'âSæ5†ûD`Œ2äå³(T','êHˆƒè*CB˜Ÿ„=ÏŒQ',2,']7„®,jBsƒfÃÿx'),
(1,'â®;«¼DÛ¿c%ÍôÖ…','jïùƒuKÚ Š¯2hó',1,'«“¼k×ÂJ{¬±QL“İ'),
(1,'ä­i[½O¢ söÄgDãZ','Ä)®İ.M¨­y™}gæÿ.',1,'üœèzKOk‚ùÆEpK*C'),
(1,'åN†jXSKÖœhéó¸÷','™`Ì·¢E\"¢w{µS@ ',1,'m™m¡ŸGu¶,×²®üĞ'),
(1,'åÁ\0n[F–t‹d‡U.','¨ÀÈSW\\BÉ¤#üä-æ',1,'İñ\0âLæ¢´p_siƒO'),
(1,'åÁ¡µÏfI¥_u³ Ü6','«cõáCÉJ8†±9Ü)½¼',1,'‚	á6‰,Måš„ş¦\'ï«'),
(1,'çqtümH®†3½oÔöá','¡R—\nİ­H·´yÓ»´wx',1,'Q~öèMA¤.Ú½*ÑµÃ'),
(1,'è\\¶èTÁC>†›FT„ãQ','<—äZGˆ™W€íŠÜë‚',1,'¦»-9©ÏN†ºıÂºé­Î'),
(1,'èƒMÆHÓÚ¼\\¡^Ô','ŞØ3~¬LÒ¹Zëõx˜;›',1,'ÚòîR@M™|Nş\n£'),
(1,'éˆÎ…öIAe¢•lKŞ','€şŠï\rœC¡Ø’•ÓÀ”',1,'‰E÷Hf¹÷–(Uîi,'),
(1,'éÇnŸä¡H°¯ãÀ?Ã‰äs','EkM*5Cğ¡\0¡ØB;hÓ',1,'íè~WcîJ©·Rò†úUğ'),
(1,'êÍAÒ\'NF˜„Œ%­ÔŒK','|±XDĞI†¹^::8',1,'ğ1·’â+MÑ¤è_@mg»'),
(1,'ë²¥‹N¸©³2¬—‹?i','ğğG@ü¶\rVøXxu2',1,'ÏŠ2) ¾IÙº”U²-ü~_'),
(1,'ìOË¶ãF}´>Äƒoï@','´­97rK<©¨…zã€8×',1,'ƒä›ÑÍŸFl¸2­+ã³÷'),
(1,'ìsX‰¿\0N™¤N=¡ì\\ôa','XUDŒ¿8K¡wˆ’{k',1,'V÷loÌM›™ïÿQzU'),
(1,'íaˆÜôÕK÷œ‡vìÜÏ5ì','r \"ÇäI}•3X²úuG',1,'(â+[);FU¼²Pbf‘b'),
(1,'îB_j€ME£ŠˆÖ*ƒS','‡pJI’4uFÒÏ',1,'ed£OS´Å‘èê.}'),
(1,'î¬.Ù†Ø@i¶eÃ„ıE','~¬\'âÍDùŠñeº’',1,'eƒ¯ê Fj¯@èl›?'),
(1,'îç¬4xD“c3ÄDl™','‹ºãVDK7™c‹b šš™',1,'¨#’nĞ”FŒ‰\0PÀ}„ '),
(1,'ïŠ¥åÂÕKƒŸß÷\n°s6','w«hó÷¼CZ²MíÀÿ®\n½',1,'ÿgĞÒÉ¡J\\¡=¼z—H¡E'),
(1,'ñ|äÌ•ÀM–IMÂí¿Î£','\r•¡±¤¶N›–è\r‰á²',1,'şÁIf|›EK€oOF>W]'),
(1,'òtÄRæ›Hç½9ÓóMrA','ntĞÂ}ƒK£’´wPb‚',1,'ä´ Ë3ÕBo>ãDŸ²Ø'),
(1,'ó<n¾iLs–ÎlîUµ)C','eíîûO•@Q…À+ğ§Q0õ',1,'ªìg\\ŞO\rº³mâ$ˆ'),
(1,'óÓŒÙ<ÄB»½›ü6¤´\'U','5{è¯ÃOA±×ãÜùµw‹',1,'‘¦ª0U­Eğ¾vËT—ÉJú'),
(1,'ô\ZãèA@†CSìéÁâÈ','zJví}HJ’HÏaš{Ê',2,'68ş;‚:Mñ„¸É¼{[Ë'),
(1,'ô\ZãèA@†CSìéÁâÈ','Ú‹¿%DæšÔ6&kÿ',1,'¢ŠGù	E3·ta3Í×'),
(1,'ô\\†\0Os‡%\0$\r\0','¨T)G•GUƒr	X&º’',1,'ù‚	X«Mõ™Ç\nÁ¾Ò'),
(1,'õ\"%h¡E¹¬ä@ZØyÈ‰','.î~šŸ Kæ¦+÷A7U',1,'Õ×3ÀõG–›?4ÆaLÈ'),
(1,'õb]gF^¦ºám\\~İÒ','`ÉOC‰‹¤Lä ',1,'²»op[JÏ«Œ¢€wñÂ'),
(1,'õ´„£n—GÊŸíÂ+/ú¡','·P™hÒE«ˆ÷ğò—fÏ†',1,'¯Q_çF$¹®@Kè8'),
(1,'öáÙ?P;@À¶|A4ßv“','CÒt”àíIR‘h#JÄÏ€p',1,'H)S\rA5L0œÇéşóL–®'),
(1,'øİ¾­uMİƒÙ‡Mè<mú','$\"÷:ÏIı·­7RÔÜ',1,'óD+6$rG@‰ßyÏà|'),
(1,'ù(UÖ(úI8ŠÂ®ÒÀ÷÷','#Ê;aäLK†¾ÌD†ÆÎ2',1,'”j¤‹qJ?ƒ\nÿÛzz'),
(1,'ù×è1ÈHVZîrX!',':7{1ÃCí«5lFŸM\\s',1,'?Ûô”Mv ê:1TD}'),
(1,'ú*ÒœØNG‡-ä»µÖ*=','9\røìñ°Cm ujÍ³†',1,'ÂÕ=MˆJëš,7ø²À?'),
(1,'ú/Š\ZŒ5Fƒ›A`û}û','£éq¹OTí`¢Á«',1,'€‘p£´E\'½ô˜ü±˜Ùü'),
(1,'úO\Z”ÔJ¹¡3\'În®Y','ºHÓİEI·¿Pyıˆı',1,'lq_\'äyNÔ©Ó÷_Ùİ€'),
(1,'ú…\'•wE*›G{2Vl','eÍ‹Ê•N˜¯ÊcŠ0\rz',1,'ñ\'•Y¡F®¡9’ë\0Ôòg'),
(1,'úßíB›pH³İ¤‰ƒÊ)','=ŸÁ0àÓM|¦P¹ólôÍ',1,'ôª9.‚‰I§ƒ—xøs‚‘'),
(1,'û1V¾]GÉGÅ/\n.UÕ','\0ØNÏBl\n)¶©ÌÑ',5,'9’ï1âkC¢ŸI.#´'),
(1,'üÑç¼M\nŠd¤ø×X','³rçŠK°Û\rôÙ½ƒ‚',1,'÷#|{’~Bİ±tÊICSø'),
(1,'ü^cw’A¨¬2fË','tTNµc@³è*å_AÂ',1,'„ª	Â‰cF)iK¶Ë-'),
(1,'ıkœNÃQLÍŠE^wÇ','o%Kdçï@Ü“#ª¯ }¶',1,'E-_lëMg§äîO;~'),
(1,'ıvàÃçO¾„¨­÷û÷\Zò','˜Ô)k~I&šFNh]‚½',1,'M×`¾2+Kh†£/¤¤xÿ§'),
(1,'ı‹úŒI@EA­^ê›§tX','9RrfXòI#ª@öÁ—v',1,'#x’¤Mi›Ö”á¢Ìs'),
(1,'ş/Éƒ„Fz¦\"šú(»','!˜ê™AÔD–	z„a+È',1,'ú/˜–ôõ@¸>®O\r–ëè'),
(1,'şn D#G®0è	Åv','fQYb¬FÏ¨EÆÿ(³ø',1,'C˜ià¸C¹¼€L8eñ'),
(1,'şÉ{µ”îH\rÿYt±¶—Ÿ','P„õµò®MaµjÉ«Zû½',1,'‚ùj¸•MS’ÕòüÏÁÎ'),
(1,'şÏ¾²h@C¬à áÍÑÌ','Yè²™((L\"—çşåÛ=²ö',1,'x %\Z\nA¼…*yšù'),
(1,'ÿâõàÚÀDn¯Şcw5Êb¡','†ïL;¤E¸½Ğ%ô™5aƒ',1,'ı&,-FÇÏIJŸv');
/*!40000 ALTER TABLE `disc_in` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `disc`
--

DROP TABLE IF EXISTS `disc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `disc` (
  `disc_id` binary(16) NOT NULL,
  `type_disc` text DEFAULT NULL,
  `format` text DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`disc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disc`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `disc` WRITE;
/*!40000 ALTER TABLE `disc` DISABLE KEYS */;
INSERT INTO `disc` VALUES
('\0ØNÏBl\n)¶©ÌÑ','feature','DVD','Politiskolen 5: PÃ¥ Miami Patrulje â€“ Movie'),
('\0²ÍrİGS­xs›OÁö','feature','DVD','Selskapsreisen 2 â€“ Movie'),
('è™Ù2IÖ‘€x8+R','feature','DVD','Sleepless in Seattle â€“ Movie'),
('ò7Ğ˜”N•~S±ÉšW','feature','DVD','We Own The Night â€“ Movie'),
('™»Áœ@Všº’ÜõCs','feature','DVD','The Manchurian Candidate â€“ Movie'),
('ŠSÍØIö•`#EÕ\"´','feature','DVD','Cleaner â€“ Movie'),
('*ÕMII»¹Åkk5.¡','feature','DVD','Outside the Law â€“ Movie'),
('M’CÖNŠ’´,Ô»\r¥Ì','feature','DVD','Crouching Tiger, Hidden Dragon â€“ Movie'),
('Œ?ïljBƒ€¿wõ1R³s','feature','DVD','Master & Commander: The far side of the world â€“ Movie'),
('!aûVÎ@Bµ¬E&ƒè›','feature','DVD','Metro 1 2 3 kapret â€“ Movie'),
('î½<©…O.¥PÖ–ô‰@È','feature','DVD','Shortbus â€“ Movie'),
('¨Î>öDÜ«R\'cI–S•','feature','DVD','Stranger than fiction â€“ Movie'),
('4STãyJµ³âÆÓ°Şó\r','feature','DVD','See No Evil, Hear No Evil â€“ Movie'),
('mªKHDA«åXw~Ql¸','feature','DVD','North Country â€“ Movie'),
('—ù‘ş&L;¤ Q‰Ÿ»8','feature','DVD','My sister\'s keeper â€“ Movie'),
('`’ÑĞLÄ‡³ámö§&™','feature','DVD','Save The Last Dance â€“ Movie'),
('„ŞŸiHh‚·ñ(ÌÎDš','feature','DVD','Inside Man â€“ Movie'),
('´­97rK<©¨…zã€8×','feature','DVD','Bowling for Columbine â€“ Movie'),
('	½ÎcşG¾›Øûï Z','feature','DVD','Air Force One â€“ Movie'),
('	0óâ6%L“„Pü†¸:','feature','DVD','Operation Dumbo Drop â€“ Movie'),
('	€$nZhKt•EÀ!Ëš','feature','DVD','Knight and Day â€“ Movie'),
('\nînÓİ­G¡§ÄÎé\\ä0','feature','DVD','Politiskolen 7: I Moskva â€“ Movie'),
('ÆR\0åşEW‰7±\ZÜÆFø','feature','DVD','Men of Honor â€“ Movie'),
('Şx,ehIB‘àŞ–h›}','feature','DVD','Final Destination 2 â€“ Movie'),
('\r•¡±¤¶N›–è\r‰á²','feature','DVD','Evig Solskinn i et Plettfritt Sinn â€“ Movie'),
('\rŠWTL3‰™o>\"T.','feature','DVD','The Third Miracle â€“ Movie'),
('\r¢sšáHt¥õØò\rR=>','feature','DVD','From Paris with love â€“ Movie'),
('h„~iIh±%Ÿç74eÙ','feature','DVD','Welcome home Roscoe Jenkins â€“ Movie'),
('AÁ€ã¨N§ÊF‹_9œ','feature','DVD','Meet Joe Black â€“ Movie'),
('«o1(B¥P&»—©E','feature','DVD','The Contract â€“ Movie'),
('„Û2AmFò¿}zOå^','feature','DVD','Wedding Crashers â€“ Movie'),
('ğğG@ü¶\rVøXxu2','feature','DVD','Home of the brave â€“ Movie'),
('<—äZGˆ™W€íŠÜë‚','feature','DVD','Hide and Seek â€“ Movie'),
('8ÕR?•@Øª»JEXo3','feature','DVD','Midnight Express â€“ Movie'),
('^õù©Lóˆ´T9•nP','feature','DVD','The Other guys â€“ Movie'),
('f–©üB[¢)âoD·&\\','feature','DVD','The Invasion â€“ Movie'),
('¸\ZG6½Hã¨	JgPÂ','feature','DVD','Fire on the Amazon â€“ Movie'),
('6˜Ø«H\r–QgÏM–Ï)','feature','DVD','Crank â€“ Movie'),
('-o«‡YH‰sôôxw','feature','DVD','Imagine that â€“ Movie'),
('¤3M°NCœŒF•nÃ¯\'Î','feature','DVD','Casanova â€“ Movie'),
('šœôNIB= ,cX9õ','feature','DVD','The Woodsman â€“ Movie'),
('„¡İL@’!çE÷g#','feature','DVD','Coach Carter â€“ Movie'),
('2³|E§¹hœb','feature','DVD','Olsenbanden og Dynamitt-Harry pÃ¥ sporet'),
('=d]®K—ŒÉ?ÀlkH','feature','DVD','Tolken â€“ Movie'),
('ğßk„`G#©à¬mTÍ3','feature','DVD','Olsenbanden: Operasjon Egon'),
('\ZÂ‘¤Hä°Ï`®Q','feature','DVD','Airplane II: The Sequel â€“ Movie'),
('\Z/Q –H½¬ uù‡ÂMÁ','feature','DVD','Next â€“ Movie'),
('\ZÀdû‹AFhÂ¨	}wÁ','feature','DVD','Along came Polly'),
('‡pJI’4uFÒÏ','feature','DVD','Remember the Titans â€“ Movie'),
('FG½÷á@—°¯Ga–&O:','feature','DVD','Coyote Ugly â€“ Movie'),
('»aİ»Oè²A_YùNe','feature','DVD','Ladder 49 - Fanget i flammene â€“ Movie'),
('#»D¦ºÖ],¤','feature','DVD','A Casa nostra â€“ Movie'),
('e(èX~KJŒ©º€µÉò)','feature','DVD','Stand By Me â€“ Movie'),
('~¬\'âÍDùŠñeº’','feature','DVD','FÃ¸dt 4. Juli â€“ Movie'),
('£vS	‹AÅŒ´nHÿYÎ','feature','DVD','The New World â€“ Movie'),
('¹1 ¦rG…˜ãTy˜ñÚ¦','feature','DVD','Bienes hemmelige liv â€“ Movie'),
('Î¼S! L—¸šåB\'ì','feature','DVD','Mr. Brooks â€“ Movie'),
('³rçŠK°Û\rôÙ½ƒ‚','feature','DVD','Amistad â€“ Movie'),
('tTNµc@³è*å_AÂ','feature','DVD','Ground Control â€“ Movie'),
('´”Ï¥ñCŞ¸+Â‚_“Ô','feature','DVD','The House bunny â€“ Movie'),
(' 	ë[4CìÖ[½„ò$','feature','DVD','Reprise â€“ Movie'),
(' `\Zœ`AŸŸcd_jÊ','feature','DVD','Olsenbanden og Dynamitt-Harry gÃ¥r amok'),
(' Ë»±B¬¤÷E”ø\'1','feature','DVD','Flugten Fra Alcatraz â€“ Movie'),
(' K»!Ü(B3¸Q®Ç”¿¥‰','feature','DVD','Gran Torino â€“ Movie'),
(' ¹‰]¸Gk©àv«“%ú','feature','DVD','Hard Candy â€“ Movie'),
('!˜ê™AÔD–	z„a+È','feature','DVD','Rules of Engagement â€“ Movie'),
('!å&º“õEÆ¸ÈŸ\\¢Úİm','feature','DVD','Herbie: Full tank â€“ Movie'),
('!ìß\ZMÛÚÇÆşÌÆ','feature','DVD','The Firm â€“ Movie'),
('\"]øui­D‹——ûÓÂ','feature','DVD','Olsenbandens aller siste kupp'),
('\"Û†HRE	ª´Ô\0=g„‡','feature','DVD','Alle mine kjÃ¦re â€“ Movie'),
('#„¸¬¾H~‘â)ÓoIWŠ','feature','DVD','The Sentinel â€“ Movie'),
('#Ê;aäLK†¾ÌD†ÆÎ2','feature','DVD','Slipp Jimmy fri â€“ Movie'),
('#Îìz\r«Oš°a\"¹ÿèâ\Z','feature','DVD','Regnmakeren â€“ Movie'),
('$\r¢uÇáO/£)¥Ó*','feature','DVD','Transporter 2 â€“ Movie'),
('$õu°D®‡¯[ïÌGË','feature','DVD','Iron Man â€“ Movie'),
('$\"÷:ÏIı·­7RÔÜ','feature','DVD','Gamle menn i nye biler â€“ Movie'),
('&£å‰M«ôßÙCD¹ì','feature','DVD','The Perfect Score â€“ Movie'),
('\'ãÇ›¡÷Mù ·¤›@ªé','feature','DVD','Full Pakke â€“ Movie'),
('(õüù¨qFF¡ I¿ÎÕ4','feature','DVD','The Forgotten â€“ Movie'),
(')Ü`×5Ai¿|ë²b','feature','DVD','Agent Cody Banks 2: Oppdrag London â€“ Movie'),
('*\rÂB©C@ƒ»F–ï»#¬','feature','DVD','The Twilight Samurai â€“ Movie'),
('+©!ºLoNoº¨C/Ï','feature','DVD','The Quiet â€“ Movie'),
('+ã“ûL›¿KYJ£{Ë–','feature','DVD','American History X â€“ Movie'),
(',	Ö ü4E€ 5ºZ¨&ù','feature','DVD','Dead Man Walking â€“ Movie'),
(',p‰¼½èAN‹Û¾á/rt','feature','DVD','LystlÃ¸gneren â€“ Movie'),
('.ÓïCÇlCù)ñŒ¯©–I','feature','DVD','Wild Roomies â€“ Movie'),
('.î~šŸ Kæ¦+÷A7U','feature','DVD','The black balloon â€“ Movie'),
('/lÜA©òGKaó•¨›ó','feature','DVD','Hollywood Homicide â€“ Movie'),
('07BèGØ‡Ğ4º{c','feature','DVD','Malcolm X â€“ Movie'),
('1ºYşO²Hb\'B}','feature','DVD','The Island â€“ Movie'),
('1ïÒ0nˆHœ\0`R!¨j','feature','DVD','The invisible â€“ Movie'),
('2*ÏOÇFB”x¹H£Yù•','feature','DVD','Things We Lost in the Fire â€“ Movie'),
('2Fq^L¢«+m>‡‘\0è','feature','DVD','Mikkes jul i Andeby â€“ Movie'),
('2ë†Î9ğO5¦ºşĞ^qX','feature','DVD','American Pie Presents: The Naked Mile â€“ Movie'),
('3¼şi;TL€—XEı‚1','feature','DVD','There Will Be Blood â€“ Movie'),
('3è‡\"ÃïF/–¶¼ÿı»','feature','DVD','The Perfect Storm â€“ Movie'),
('4ÆéÁZ-Kô»ÈTx˜ï','feature','DVD','The Shawshank Redemption â€“ Movie'),
('5{è¯ÃOA±×ãÜùµw‹','feature','DVD','AvslÃ¸ringen â€“ Movie'),
('5k<LB¡“ˆ‰m8','feature','DVD','DÃ¸delig vÃ¥pen 3 â€“ Movie'),
('5ÿcLšRNÊ©}İb¼şa','feature','DVD','Just My Luck â€“ Movie'),
('6NÜ˜¢C…kŠL_ªÉ','feature','DVD','The Librarian III: The Curse of the Judas Chalice â€“ Movie'),
('7)Q?ëHÂŒu ÀU¶Š','feature','DVD','Tsotsi â€“ Movie'),
('7À,‚ZŸCI«öIE‹¹M','feature','DVD','Indiana Jones og Jakten pÃ¥ den forsvunnende skatten'),
('8±= Z³KG–®!ã*†','feature','DVD','Boat Trip â€“ Movie'),
('8û¥{ÑE®Œˆì€„á','feature','DVD','The Matador â€“ Movie'),
('9\røìñ°Cm ujÍ³†','feature','DVD','The Transporter â€“ Movie'),
('9G%û9İG³«ŠŸ#*…t©','feature','DVD','When The Last Sword Is Drawn â€“ Movie'),
('9RrfXòI#ª@öÁ—v','feature','DVD','Du har m@il â€“ Movie'),
(':7{1ÃCí«5lFŸM\\s','feature','DVD','Taxi â€“ Movie'),
(':Uå g÷M¬”HÛšœ‘¶t','feature','DVD','Hot shots!'),
(':v+§ëiLH¾\nØIé€<ë','feature','DVD','Ikke Uten Min Datter â€“ Movie'),
(';wã}%ÚM®™÷-Œ8e¿Û','feature','DVD','Syv sverd â€“ Movie'),
('<lQu@=´³·l)2²b','feature','DVD','Skin â€“ Movie'),
('<,Ôb\nOæ©H-²É¼','feature','DVD','Selskapsgolferen â€“ Movie'),
('=së¦—éIÌš¡•@#-','feature','DVD','When Harry Met Sally â€“ Movie'),
('=™ımÀF…’ù*Üë3','feature','DVD','Operation Takedown â€“ Movie'),
('=ŸÁ0àÓM|¦P¹ólôÍ','feature','DVD','VÃ¥r, Sommer, HÃ¸st, Vinter... og VÃ¥r â€“ Movie'),
('>	ÿ^5ÛK.¯’ÆÕtşt¯','feature','DVD','Snatch â€“ Movie'),
('>¢ä²CŒ±µÃ„Îâ','feature','DVD','Flightplan â€“ Movie'),
('>•U¹«˜L]¹ÒM(eMa]','feature','DVD','Olsenbandens siste stikk'),
('>¿YÖÿ‹KŠ‡zÉc+7œ','feature','DVD','Revolutionary road â€“ Movie'),
('>Ï0á\ZD“;·¿%éİÛ','feature','DVD','The Blair Witch Project â€“ Movie'),
('?´îØ@z‹!ç¹7jL/','feature','DVD','Eventyr i Alaska â€“ Movie'),
('?î‰ûïNo£”ŠÒ$åÎ','feature','DVD','Birds of America â€“ Movie'),
('@HrBîO\'©¿–Ï¶Á','feature','DVD','Courage Under Fire â€“ Movie'),
('@KËÍWƒO«”†ˆ¡ÌŞá¶','feature','DVD','Ronin â€“ Movie'),
('A~¹§8&C©ø’–¦Éõ','feature','DVD','Sex, Lies & Murder â€“ Movie'),
('AÿÃ+4ëB‘€” œ*‡o','feature','DVD','The Babe â€“ Movie'),
('BÓ®ŒùF=‚£P(kv','feature','DVD','Edge of Darkness â€“ Movie'),
('CÒt”àíIR‘h#JÄÏ€p','feature','DVD','American Pie â€“ Movie'),
('D„Ÿ6´–Fj§æ¦ ÈË÷','feature','DVD','Olsenbanden for full musikk'),
('DİRş8BÎ¤S}°ôC>Í','feature','DVD','State of Play â€“ Movie'),
('EkM*5Cğ¡\0¡ØB;hÓ','feature','DVD','Lock Stock & Two Smoking Barrels â€“ Movie'),
('EŞVõE»k5ìœ–Ã','feature','DVD','Pride And Glory â€“ Movie'),
('FM#9ûy@K´}E.Í„','feature','DVD','Julenissen â€“ Movie'),
('Fk ÄÜAJ…‘Å˜#ì9F','feature','DVD','En dag i livet â€“ Movie'),
('GšhR&F\"œÔVÖ*jó','feature','DVD','Cable Guy â€“ Movie'),
('G$£è†GC™D©$’','feature','DVD','You, me and Dupree'),
('Háiò>D¿˜Ÿ¹°úğ$X','feature','DVD','Life of Brian â€“ Movie'),
('HôÃ’ç#@‹Ñr]oåú','feature','DVD','The Thomas Crown Affair â€“ Movie'),
('I:%ÏH\0GJ«÷°¬[¸ôg','feature','DVD','DÃ¸delig vÃ¥pen 4 â€“ Movie'),
('I‚‹Q\nªI—¿Ê*¦Ş=à¿','feature','DVD','Hancock â€“ Movie'),
('IÓ»I0XDĞ«Âáã“¨¯','feature','DVD','Lilja 4-ever â€“ Movie'),
('J‚­†Ep©×k	azŠ@','feature','DVD','Zathura - et romeventyr â€“ Movie'),
('J›Ùù£xH\n‚÷fµ¼r5©','feature','DVD','Mannen uten identitet â€“ Movie'),
('JÉ§³ÁÁGC½29#Yzµ/','feature','DVD','He Got Game â€“ Movie'),
('Kä:.‹N¨œvHŒö†','feature','DVD','Enemy of the State â€“ Movie'),
('K™…ü¬aI†*Ûb$M¯','feature','DVD','Ploy â€“ Movie'),
('KÂÑö@î”I1ÌÕ®`','feature','DVD','The Break-up â€“ Movie'),
('Lô7©O¢OM’Ä-îı6‚X','feature','DVD','Absolute Power â€“ Movie'),
('MH—M¨¥¥OW©ã','feature','DVD','Kamilla og tyven 2'),
('M\'ç¶AÚº	™|§½Bè','bonus','DVD','Box-set â€“ Bonus'),
('Mœâ¥\"E¼¡2(o','feature','DVD','Desperate Measures â€“ Movie'),
('M¸÷†O‹¼l÷ ´ i','feature','DVD','United â€“ Movie'),
('MÀù÷ÙîHÆ˜bŞ	A~Û<','feature','DVD','Running Scared â€“ Movie'),
('MØë9ÓãD€€£Û¼[>é','feature','DVD','Erin Brockovich â€“ Movie'),
('N\rİN{‘Å˜Ã¹âŒ','feature','DVD','DÃ¸d snÃ¸ â€“ Movie'),
('Oİàu‡H<›´\ZNëv±','feature','DVD','Politiskolen 4: Borgere PÃ¥ Patrulje â€“ Movie'),
('OY”ŞA3C>¤°hà’¡','feature','DVD','Hidalgo â€“ Movie'),
('Owz\'ãUM@Ÿ& i€Ì\"','feature','DVD','2 Fast 2 Furious â€“ Movie'),
('P:-O¡ÆFf»9h÷','feature','DVD','Half Nelson â€“ Movie'),
('PD\n03G\"¼ÒŞ•ËÁ.6','feature','DVD','Reservation Road â€“ Movie'),
('P„õµò®MaµjÉ«Zû½','feature','DVD','Boys Don\'t Cry â€“ Movie'),
('Pè~¸J¤§w&U5Å','feature','DVD','The Longest Yard â€“ Movie'),
('Q\Zˆ˜ÂÒN¤·väÿO’T­','feature','DVD','Lakeview Terrace â€“ Movie'),
('RV€?XK6¬àlc>¹','feature','DVD','Olsenbanden og Dynamitt-Harry mot nye hÃ¸yder'),
('RgÌ¡ÏM‹]¸_÷P','feature','DVD','Olsenbandens siste bedrifter'),
('RÀ—ü—DŠ?»©åB?','feature','DVD','Alvin og gjengen â€“ Movie'),
('S›ìß÷˜A•¶ø$„/ÿ‰','feature','DVD','Olsenbanden mÃ¸ter Kongen og Knekten'),
('S¾À¤áôC©¢²[A<ç','feature','DVD','Mumien â€“ Movie'),
('U>…üC†·Ÿğbò','feature','DVD','Transformers â€“ Movie'),
('U—ü>JO³•=ÎŸY','feature','DVD','Spider-Man â€“ Movie'),
('VHDz‹O¤‡t«ÌÅ¹¬','feature','DVD','Stargate: The Ark Of Truth â€“ Movie'),
('WSÌ;.NÆ•‚k£¾ËP','feature','DVD','Traffic â€“ Movie'),
('Wqÿú¿EÇªv¼$ÇÖ','feature','DVD','Stopp! Annars skjuter morsan skarpt â€“ Movie'),
('X!oA3Ldmh\"üt¸Û','feature','DVD','FÃ¸rste Oppdrag â€“ Movie'),
('XUDŒ¿8K¡wˆ’{k','feature','DVD','Slipstream â€“ Movie'),
('Yè²™((L\"—çşåÛ=²ö','feature','DVD','Face/Off â€“ Movie'),
('[@¯Ş¢×G¦«”$q+','feature','DVD','Indiana Jones og De fordÃ¸mtes tempel'),
('\\k‘½Z­G„ŠX)-æ~C','feature','DVD','The Heartbreak Kid â€“ Movie'),
('\\˜Ü] ²CÈºŠÜ­á','feature','DVD','Dante\'s Peak â€“ Movie'),
(']zÛríùB<–/m–GÒ','feature','DVD','Capote â€“ Movie'),
(']Æ4-UF¤Ÿ–­¤ÈëQ_','feature','DVD','Der elven renner â€“ Movie'),
('^®3ªjJ§^È÷€€â','feature','DVD','Den Siste Tempel Ridder â€“ Movie'),
('__ëYÓ]AB™^˜Ñºhöƒ','feature','DVD','Lange Flate BallÃ¦r 2 â€“ Movie'),
('`ÉOC‰‹¤Lä ','feature','DVD','FAST & FURIOUS â€“ Movie'),
('bŞxr\0fK¾…øÊ5q‹Ô','feature','DVD','HÃ¥kon HÃ¥konsen â€“ Movie'),
('cŞ¤]3Jâ¶—_»nw','feature','DVD','Pusher â€“ Movie'),
('efòÙ•Kb¿w2‹5©Ï','feature','DVD','Villmarkens SÃ¸nn â€“ Movie'),
('eÍ‹Ê•N˜¯ÊcŠ0\rz','feature','DVD','M:I-2 â€“ Movie'),
('eíîûO•@Q…À+ğ§Q0õ','feature','DVD','Last Embrace â€“ Movie'),
('fQYb¬FÏ¨EÆÿ(³ø','feature','DVD','The Butterfly Effect â€“ Movie'),
('fEĞVEXJ%†vÒ°·Ş† ','feature','DVD','S.W.A.T. â€“ Movie'),
('gr4ÃNò€Ç’®Ys=Ë','feature','DVD','Chasing Liberty â€“ Movie'),
('gäujşO7‹ê®A‡­^','feature','DVD','The Truth About Charlie â€“ Movie'),
('hùÀÀò¼A¶‰ã]sª·5','feature','DVD','The Bourne Identity â€“ Movie'),
('i“²tECŒÕk>g','feature','DVD','Easy A â€“ Movie'),
('iœ8n}ÂL÷šÎù­Î£€â','feature','DVD','The Score â€“ Movie'),
('iÌ1ÔwYD`†Í13–÷Ù','feature','DVD','In My Country â€“ Movie'),
('jî8¤İwEZ¸É±#°±N„','feature','DVD','High Crimes â€“ Movie'),
('jïùƒuKÚ Š¯2hó','feature','DVD','Rails & ties â€“ Movie'),
('k•’çÃÉLæ·ì&‡zôp','feature','DVD','DÃ¸delig vÃ¥pen â€“ Movie'),
('k¢\'9î-LZ•œÓƒ¶Y,','feature','DVD','Eastern Promises â€“ Movie'),
('m>p*b@¸Räû½ıÀ','feature','DVD','Closer â€“ Movie'),
('ntĞÂ}ƒK£’´wPb‚','feature','DVD','It\'a complicated â€“ Movie'),
('o%Kdçï@Ü“#ª¯ }¶','feature','DVD','The Karate Kid â€“ Movie'),
('ot)¾eJ¹—Ìås¤…+','feature','DVD','Utopian society â€“ Movie'),
('pZ‹½ö‰N¥’\\‡’x¸','feature','DVD','Den innerste sirkel â€“ Movie'),
('q­DäJMI¤UG“ÏNJ','feature','DVD','The Siege â€“ Movie'),
('r \"ÇäI}•3X²úuG','feature','DVD','Overhengende fare â€“ Movie'),
('rğõİ.Ké·Ò®ÛıA','feature','DVD','Kill Bill: Volume 1 â€“ Movie'),
('rş¥„ÿ@F›¾$(	zi/','feature','DVD','Adele and the secret of the mummy â€“ Movie'),
('sPÉåxJ»¥	J„á','feature','DVD','Pitbullterje â€“ Movie'),
('s`&\\ÒN·Œ_µğ_àƒí','feature','DVD','16 Blocks â€“ Movie'),
('t U:âfOà©[fÜà¾“','feature','DVD','Matilda â€“ Movie'),
('t\'ì‹tùF§úíN§×¥]','feature','DVD','The Secret Garden â€“ Movie'),
('t:tZËA‹¤\Z.Ëv#õ','feature','DVD','Heavens Prisoner â€“ Movie'),
('tNëÂè˜B¸ík$¦ï^›','feature','DVD','Vannliljer â€“ Movie'),
('tkmÕ«MÌ“ã%~Z†ò„','feature','DVD','Twister â€“ Movie'),
('t¼¼6Ñ@ş·Ù1#`—n','feature','DVD','The Last King of Scotland â€“ Movie'),
('u;QóàcN>ˆ¯{´$MëF','feature','DVD','GymnaslÃ¦rer Pedersen â€“ Movie'),
('uœ&ÒE¤³üÅğ¾Ş«á','feature','DVD','Coraline og den hemmelige dÃ¸r â€“ Movie'),
('w«hó÷¼CZ²MíÀÿ®\n½','feature','DVD','NÃ¥ er det Jul - igjen â€“ Movie'),
('xe^¹tGŸ¡üˆ±+ÍÇ','feature','DVD','Gone Baby Gone â€“ Movie'),
('xÆ,ş6B-¶Ç}è†ÎR','feature','DVD','Rain Man â€“ Movie'),
('yÃ*NK¼ŒåœK¡uµ','feature','DVD','Crash â€“ Movie'),
('yÆ®tÑhLÀ»É%j·ŸÙ','feature','DVD','Juno â€“ Movie'),
('zJví}HJ’HÏaš{Ê','feature','DVD','Mannen med en rÃ¸d sko â€“ Movie'),
('zõ‚´óÇ@†²ãÓ}kØ','feature','DVD','Road to Perdition â€“ Movie'),
('|;\r®—øE¸¤H,\n§a','feature','DVD','Volcano â€“ Movie'),
('|±XDĞI†¹^::8','feature','DVD','Beyond Borders â€“ Movie'),
('}KòoéG¬Ÿ¸÷m} ŠU','feature','DVD','I, Robot â€“ Movie'),
('~	’F‡>F´ï@SG','feature','DVD','Jalla! Jalla! â€“ Movie'),
('*­Ãƒ†Må¡ÛøŞ6tG','feature','DVD','American Pie Presents: Beta House â€“ Movie'),
('wº®-Húš2À´ˆu£Ô','feature','DVD','U.S.S Nimitz - Lost in the Pacific â€“ Movie'),
('€Ó[ñB¯˜A{sÊn¥','feature','DVD','88 Minutes â€“ Movie'),
('€YĞJ¬Jê«®¿Àüà˜','feature','DVD','National Security â€“ Movie'),
('€nhŠKB–ªqJ?YY','feature','DVD','Olsenbanden og Dynamitt-Harry'),
('€™É&†M*ˆá1Wyá','feature','DVD','Stealth: Den skjulte trusselen â€“ Movie'),
('€­–âqXK7¹6»¼È4—','feature','DVD','Bare skyer beveger stjernene â€“ Movie'),
('€şŠï\rœC¡Ø’•ÓÀ”','feature','DVD','Tommys Inferno â€“ Movie'),
('‚[Ò„ßWJÖŒwAí¿F	‘','feature','DVD','Daylight â€“ Movie'),
('‚mñË¿Ö@¶©T¯b	u','feature','DVD','Runaway Train â€“ Movie'),
('‚àÑEß¥Ix@=`©','feature','DVD','I Kina spiser de hund â€“ Movie'),
('ƒ§[…Iª»äeĞ!ç','feature','DVD','Ken Park â€“ Movie'),
('„5ıö„E¶¥T_¸Š™+f','feature','DVD','Bruce Almighty â€“ Movie'),
('„Yò¸ Kå¡Ö.ûy_è','feature','DVD','SOS En seilskapsreise â€“ Movie'),
('„Ò²ÌòF@J‡µT)\\cÚÕ','feature','DVD','Hotel Rwanda â€“ Movie'),
('†ïL;¤E¸½Ğ%ô™5aƒ','feature','DVD','Fred Claus â€“ Movie'),
('‡>2¶0ÉN™¢€6Â','feature','DVD','Chaos theory â€“ Movie'),
('‡SÜ°I:‹Ôun¾ö\"','feature','DVD','I Siste Sekund â€“ Movie'),
('‡Ó\'nMüîû}ç8øË','feature','DVD','Of Mice and Men â€“ Movie'),
('‡İòm†?Iˆ¥±Â_OÖt','feature','DVD','Hjemme alene 2 â€“ Movie'),
('ˆ»¤b­ÔCá˜ZÒ[¢ƒc','feature','DVD','Veronikas to liv â€“ Movie'),
('‰ÛJ1H ¢Éÿ²YÖš','feature','DVD','American pie 2 â€“ Movie'),
('Š!u¿“SI&´1BbÙÕ;','feature','DVD','Gudfaren del III â€“ Movie'),
('Š@®~.BÃ¶®1$—€™Æ','feature','DVD','Flyvende Dolker â€“ Movie'),
('Šsu pE\\¶Á„%Q1E','feature','DVD','The Constant Gardener â€“ Movie'),
('‹›cüEAe²-Æ[Ö93ı','feature','DVD','Blind Horizon â€“ Movie'),
('‹¶\'uCÏCVœË','feature','DVD','Politiskolen 3: Tilbage Til Anstalten â€“ Movie'),
('‹ºãVDK7™c‹b šš™','feature','DVD','Stargate â€“ Movie'),
('Œ7Ú›‘tK«ôŸ·oÈ','feature','DVD','The Karate Kid Part II: Sannhetens Ã˜yeblikk del II â€“ Movie'),
('£éq¹OTí`¢Á«','feature','DVD','D-Tox â€“ Movie'),
('¼I‹†Iˆºv9´‚','feature','DVD','The Final Cut â€“ Movie'),
('ª¬HõpNİ¶uæãZ}]~','feature','DVD','Stargate: Continuum â€“ Movie'),
('ÔçY/ĞGä•Xëÿ9','feature','DVD','Englenes by â€“ Movie'),
('º,\0‡Oxá3;aÔVj','feature','DVD','Falskmyntnerne i Sachsenhausen â€“ Movie'),
('’ØÒú-E,“ Ğ{l¶½','feature','DVD','Selskapsreisen â€“ Movie'),
('’ñÄÍI@b‘;øÛ`9‰','feature','DVD','De ubestikkelige â€“ Movie'),
('“9(¹Å·Dˆ­\"’ c*}Z','feature','DVD','King Arthur â€“ Movie'),
('”#8‹ôHÏ´®Ê_35P&','feature','DVD','Yes Man â€“ Movie'),
('”ZÂå%I@¢”\rMNÿ','feature','DVD','Wild Things 3 â€“ Movie'),
('•LO¾ÚM3•ê–\r“ZÊ¢','feature','DVD','MÃ¼nchen â€“ Movie'),
('•Â?\\µ\0KöŠhTõdfÛ','feature','DVD','The Karate Kid Part III â€“ Movie'),
('•ôPX*ÖLo™S¹\0Õk}y','feature','DVD','Push â€“ Movie'),
('–É­UîâE”…wlèÖ®x\r','feature','DVD','Politiskolen â€“ Movie'),
('—@,ËùDù‰««M4L','feature','DVD','Illusjonisten â€“ Movie'),
('˜•Ê<Š2Ad“ŸÆ5²,¦+','feature','DVD','The Legend of Bagger Vance â€“ Movie'),
('˜Ô)k~I&šFNh]‚½','feature','DVD','Pusher II â€“ Movie'),
('˜ûL»-áO”æ£­~e¦','feature','DVD','Samleren Kiss the Girls â€“ Movie'),
('™`Ì·¢E\"¢w{µS@ ','feature','DVD','Bend It Like Beckham â€“ Movie'),
('™Å>ì\'pKç‘ØìEò‡ä','feature','DVD','Hot shots 2!'),
('štyú6Bw‚]ÊhîHÄĞ','feature','DVD','Frostbite â€“ Movie'),
('›Î¤z‚L±—å!€Ot±','feature','DVD','The Fugitive â€“ Movie'),
('œÔ\0­JEF·tœ3m³ò','feature','DVD','Body of Lies â€“ Movie'),
('œëÄj°@H²ÑºÚûÆ7~','feature','DVD','Giftige lÃ¸gner â€“ Movie'),
('œøa*ëYM«.t®å§','feature','DVD','Cloverfield â€“ Movie'),
('*PgL­\\FÕ!pÇ','feature','DVD','Signs â€“ Movie'),
('²\0Ÿ÷J—©;øø4¸±a','feature','DVD','De blÃ¥ ulvene â€“ Movie'),
('¿óÕMF	²7„BÌSú','feature','DVD','The Missing â€“ Movie'),
('Ÿ	IdÌE\"¦×  -/É“','feature','DVD','Casper â€“ Movie'),
('ŸGj	MÙ él»ú,\r','feature','DVD','Revolver â€“ Movie'),
('ŸÇuã²Jœ¯À\r©cù@½','feature','DVD','The Librarian II: Return to King Solomon\'s Mines â€“ Movie'),
('¡R—\nİ­H·´yÓ»´wx','feature','DVD','Curious Case Of Benjamin Button â€“ Movie'),
('£JŠÊŸÃMŞ«xÜDZî','feature','DVD','Antatt skyldig â€“ Movie'),
('£N[’†ĞL‚²?uò4ı','feature','DVD','Skredderen i Panama â€“ Movie'),
('£¥ŠçLôL#€Yw§­®+','feature','DVD','American Pie: Bryllupet â€“ Movie'),
('¤:<¦GWº9pNÖ&€Í','feature','DVD','Monstertorsdag â€“ Movie'),
('¤\"q›w½JydqãÜë.','feature','DVD','Traitor â€“ Movie'),
('¤pöVâJ4ï\n¶l…*','feature','DVD','Meet Bill â€“ Movie'),
('¥!^Õ©yFƒ‰§–63Ÿ','feature','DVD','The Lake House - Huset ved vannet â€“ Movie'),
('§!¸¬4OM#LÏµ3r','feature','DVD','1492 Conquest of Paradise â€“ Movie'),
('§õø77ÊK¯µ$´Ö–ˆßŞ','feature','DVD','The Last Kiss â€“ Movie'),
('¨¨ı2âK)£–\Z`i','feature','DVD','Shutter island â€“ Movie'),
('¨>Éi¯§N—‘Ğ3¢à','feature','DVD','The Science of Sleep â€“ Movie'),
('¨T)G•GUƒr	X&º’','feature','DVD','The Scorpion King â€“ Movie'),
('¨ƒ˜¶©¨J:^ìA¹Ÿ','feature','DVD','Entrapment â€“ Movie'),
('¨ÀÈSW\\BÉ¤#üä-æ','feature','DVD','The Chamber â€“ Movie'),
('ªedÀ¾Aì¼ÿmßÉ','feature','DVD','Sin City â€“ Movie'),
('ªkŠh@‹·J¬>û/ö','feature','DVD','Domino â€“ Movie'),
('ª¸í¿NFe·ñÂ¤ãï\"ñ','feature','DVD','Match Point â€“ Movie'),
('ªÓ\"Æ¶Dr»á sæB§','feature','DVD','The Italian Job â€“ Movie'),
('«B¤™û\nCP­\0;AÙ)_®','feature','DVD','Olsenbanden og Data-Harry sprenger Verdensbanken'),
('«cõáCÉJ8†±9Ü)½¼','feature','DVD','Metro â€“ Movie'),
('«ğÏ°SO£’Ìa\"ş','feature','DVD','13 - Thirteen â€“ Movie'),
('¬hÅ®>Mè¼÷†\\Ûõ9õ','feature','DVD','DÃ¸delig vÃ¥pen 2 â€“ Movie'),
('­ü=ÑÍEe´4Ìe$VIT','feature','DVD','Dumpet av Sarah Marshall â€“ Movie'),
('®GÑÎqB¤=1×…Ü','feature','DVD','Changeling â€“ Movie'),
('®D\0l_KT›;´4\'©Ëş','feature','DVD','Memoirs of a Geisha â€“ Movie'),
('®Îºa\0—KÂ¤f\rEgZ	','feature','DVD','Training Day â€“ Movie'),
('°$ËDaMı§ÂÃ^-§i','feature','DVD','Fanget i MÃ¸rket â€“ Movie'),
('°­üYÌ!Kn™ÃS¼xÚ\rÇ','feature','DVD','The Librarian â€“ Movie'),
('±&]WZĞAÙ”Mş?M¿°','feature','DVD','Hitch â€“ Movie'),
('³î0š™A2¤CvP¾Ë /','feature','DVD','Grace is gone â€“ Movie'),
('´HÏ2GŞMI”d¿²','feature','DVD','Nanny McPhee â€“ Movie'),
('µ¨ú¬ƒOEŒ±)–Xö','feature','DVD','The Road â€“ Movie'),
('µÇª8Dw§ÿ,,¨ k','feature','DVD','Mississippi i Flammer â€“ Movie'),
('¶-ŸCE¾tÜw¢~','feature','DVD','The Pelican Brief â€“ Movie'),
('·3#(7¿FD¼	Rq™¦','feature','DVD','Gjestene fra fortiden 2 â€“ Movie'),
('·P™hÒE«ˆ÷ğò—fÏ†','feature','DVD','Nick & Norah\'s infinite playlist â€“ Movie'),
('¸»›Á©GŒLgÓ0¸S','feature','DVD','Guess Who â€“ Movie'),
('¸×©_¹E©Ça×ÙÂ<','feature','DVD','A Murder Of Crows â€“ Movie'),
('¹j×+OŒ…™´NÊ~		','feature','DVD','The Last Kiss â€“ Movie'),
('¹T“I>vKÛŒúD]İ&','feature','DVD','Men Olsenbanden var ikke dÃ¸d!'),
('¹¿+6ºıE&«Ä Ô·šE','feature','DVD','Untraceable â€“ Movie'),
('ºHÓİEI·¿Pyıˆı','feature','DVD','Rembrandt kuppet â€“ Movie'),
('ºÎXñH@á*Õ`X+#Á','feature','DVD','The Spirit â€“ Movie'),
('»/êQF³R\\â`[Á~','feature','DVD','Hard Rain â€“ Movie'),
('»Š…	#¿@ë§ºàhXV','feature','DVD','Kamilla og tyven'),
('½0lR#K‘*9(Ù»ÛÔ','feature','DVD','Sjakalen â€“ Movie'),
('½?,œpLO´´}&Ê1¾‰','feature','DVD','Gudfaren â€“ Movie'),
('¾Qº“|L—PÛa9—Ÿ','feature','DVD','Asterix & Obelix: I Kamp Mod CÃ¦sar â€“ Movie'),
('ÁÇY³ƒCÓš‘ºïŞÉA','feature','DVD','Into the Blue â€“ Movie'),
('Áşª<p*B½¢Ş\Z$Ì’','feature','DVD','Den utvalgte â€“ Movie'),
('Â*Íä°ÇLÊ…{ÔÚ=²‚','feature','DVD','Ulvenatten â€“ Movie'),
('Ã¨Tâğ‚O‡µpõ<r¨§ù','feature','DVD','Bad lieutenant: Port of call New Orleans â€“ Movie'),
('Ä)®İ.M¨­y™}gæÿ.','feature','DVD','Driving miss Daisy â€“ Movie'),
('Ä7ú»ÿúDò¨ÕÅb!Ønt','feature','DVD','Hoffa â€“ Movie'),
('ÅÓElÖOöˆÃ`ƒÉp','feature','DVD','Nettet â€“ Movie'),
('Å*CN0PJÚÌ÷É|§','feature','DVD','Kautokeino-opprÃ¸ret â€“ Movie'),
('Å‚à­(ûC¦›Øiì*õ','feature','DVD','Final Destination â€“ Movie'),
('È*e·&CB9³÷«¯|h','feature','DVD','Fried Green Tomatoes at the Whistlestop CafÃ© â€“ Movie'),
('É¡št)’B–”$ı	’8','feature','DVD','There\'s Something About Mary â€“ Movie'),
('ÉìšW‰Dp¨*w×›\ršä','feature','DVD','In her Defense â€“ Movie'),
('ÊgŠˆH»ªü›’şÌe;','feature','DVD','For the Money â€“ Movie'),
('Ì¯Úè´I]ŒZS:Ú†!3','feature','DVD','Politiskolen 6: VÃ¦lter Byen â€“ Movie'),
('Í7ñ¼¾L­“	è ò','feature','DVD','Den fabelaktige Amelie fra Montmartre â€“ Movie'),
('Íµˆ àiA„¯Ç’5öÂn','feature','DVD','The Fan â€“ Movie'),
('Íë’ÒÙK¬µÕ0d`í','feature','DVD','Whale Rider â€“ Movie'),
('ÎJï(ÿG[©¶¬½À3Mú','feature','DVD','Flukten fra hÃ¸nsegÃ¥rden â€“ Movie'),
('ÎMY¡šO:‰@«	æû”','feature','DVD','Indiana Jones og Jakten pÃ¥ den forsvunnende skatten'),
('ÎNgvq%Lï°ÔáÌÕN\0','feature','DVD','Netforce â€“ Movie'),
('Ğ !œ‘™EÓ(ô¨rè','feature','DVD','K-19: The Widowmaker â€“ Movie'),
('Ğ° MAGLÔİ*µS+&','feature','DVD','Helsereisen â€“ Movie'),
('ÑÁ__“¥JÌ¤ >}Æ(›$','feature','DVD','Greven av Monte Cristo â€“ Movie'),
('Ò(Ú#º L¼\ZPf¦Ÿ1}','feature','DVD','Crank : high voltage â€“ Movie'),
('ÒãEn¾Ac»—<³6ˆÕ*','feature','DVD','Double Jeopardy â€“ Movie'),
('Ó-œxOG…º\0çí¤ò','feature','DVD','The Mighty â€“ Movie'),
('ÓeÓ¸£ÿMÛ¸Ú¸z–ä','feature','DVD','Hero â€“ Movie'),
('Óòd[Dàòï.‚','feature','DVD','Extreme movie â€“ Movie'),
('Ô ÎƒøC!Š¸,œ<©…','feature','DVD','Mannen uten ansikt (The Man Without a Face) â€“ Movie'),
('Õ`=üWM`œííº°«1D','feature','DVD','American Pie: Band Camp â€“ Movie'),
('Ö ù\niK]¾—\"©¡Ä','feature','DVD','The Machinist â€“ Movie'),
('Ö*¨€73Fö¨Õ‰ÚÎã ','feature','DVD','Elektra â€“ Movie'),
('Ö¬i—d\rA±œMV.‡m™','feature','DVD','American pie presents: the book of love â€“ Movie'),
('Öµš?”IB!»=ŞœêÂ„','feature','DVD','Airplane! â€“ Movie'),
('×3$ó\'çM€•¦õ]‘','feature','DVD','She\'s the Man â€“ Movie'),
('ØhÊ¨ßFt€,ç+G','feature','DVD','Eagle Eye â€“ Movie'),
('Ø°#P¿FJSÓ*°•·','feature','DVD','Miami Vice â€“ Movie'),
('Ùa…p,kNPBäH@–¦','feature','DVD','The Guardian â€“ Movie'),
('Ù°¤2”ò@¡«)aµ…øU','feature','DVD','Lara Croft: Tomb Raider â€“ Movie'),
('Ú‹¿%DæšÔ6&kÿ','feature','DVD','Big â€“ Movie'),
('Ú¡0Ê„:H\rƒ@Fª³3¬÷','feature','DVD','Hjemme alene â€“ Movie'),
('Û\n¦4ÕíAğ§=¹8ï8','feature','DVD','Lara Croft: Tomb Raider: The Cradle of Life â€“ Movie'),
('Û:JzkïFC²¹¹‰/','feature','DVD','The Assassination of Jesse James by the Coward Robert Ford â€“ Movie'),
('Ü=ì@6HiÔY¿îÛO','feature','DVD','Sammen er vi mindre alene â€“ Movie'),
('ÜVÇÖ nM‚Ïs{ËLL','feature','DVD','End Game â€“ Movie'),
('İµûĞ–H¤èÁ—-ö ','feature','DVD','Surrogates â€“ Movie'),
('İÕœ8ÎvG§·VXÖ!Úí','feature','DVD','Lady in the Water â€“ Movie'),
('ŞØ3~¬LÒ¹Zëõx˜;›','feature','DVD','Mr. & Mrs. Smith â€“ Movie'),
('âG 4‡Ú@f€Á?õ0íO','feature','DVD','Broken Flowers â€“ Movie'),
('â^\0U$F‘‹9fèrM¹¡','feature','DVD','Wanted â€“ Movie'),
('âj°*Mœœ±‡¬ÊT','feature','DVD','Kill Bill: Vol. 2 â€“ Movie'),
('â¯EçìpMA¯ÂD::|Şş','feature','DVD','Spartan â€“ Movie'),
('ãˆhbNAÔƒÃQ_i/ƒ','feature','DVD','Supervoksen â€“ Movie'),
('ä9ñe@äD…±$í]‡ÉìÁ','feature','DVD','Helt sikkert, kanskje â€“ Movie'),
('å>`¿–Dš°0à1¡Óp','feature','DVD','Rottenetter â€“ Movie'),
('æ‚³Š~J‰ ÊKì	¨Oˆ','feature','DVD','Crossing over â€“ Movie'),
('æƒı€£9GÃ†¤Ü_ÄÇÆ®','feature','DVD','Olsenbanden gir seg aldri!'),
('æÕ§CbtHb³…ÄNcÕÏL','feature','DVD','Das Boot â€“ Movie'),
('çP¥?AF«¼ïuÄì#³z','feature','DVD','D\'artagnans datter â€“ Movie'),
('è§z”$ÃIùc<‡»y','feature','DVD','Shattered Glass â€“ Movie'),
('êHˆƒè*CB˜Ÿ„=ÏŒQ','feature','DVD','Politiskolen 2: Deres FÃ¸rste Opgave â€“ Movie'),
('êÛ¯´îFo’Gİë¨','feature','DVD','The Client â€“ Movie'),
('ënù:õMŸ¤ê%êÚ†','feature','DVD','Trial and Error â€“ Movie'),
('ëõ`‚\\@©¤=/¾¹4','feature','DVD','Stekte grÃ¸nne tomater â€“ Movie'),
('ì¹œÎÒxDÈ¶jKÓ|+','feature','DVD','Den siste Keiseren â€“ Movie'),
('í*x½WCF™ü¼ş)”','feature','DVD','The Pursuit of Happyness â€“ Movie'),
('ï¥Ğ.A´¿,1ƒág«5','feature','DVD','Hamilton â€“ Movie'),
('ğk0¹çÊHç–2&bÊ}~%','feature','DVD','Tillsammans â€“ Movie'),
('ò3Ñ‹Ú6D{–´ek–ÕÊ','feature','DVD','Sherlock Holmes â€“ Movie'),
('ó’˜Ç×‘B_‹ä§¡ö','feature','DVD','Sweet November â€“ Movie'),
('ö2>kBa—‰Vz«å1','feature','DVD','Gjestene fra fortiden â€“ Movie'),
('ö÷~É`KÕ“ —J´ş“','feature','DVD','The Thin Red Line â€“ Movie'),
('÷Gíè¦Dg˜Ê&|}èè','feature','DVD','Syriana â€“ Movie'),
('÷°ö×B@C-›1µ‡½üo','feature','DVD','Olsenbanden tar gull'),
('÷Ñë-¢\'Lªíh6İ²','feature','DVD','Sheriffen â€“ Movie'),
('÷ıcÄ.C¶àã6Ã= ','feature','DVD','Dagboken â€“ Movie'),
('ù¸“F\ZIyö=fKäÖA','feature','DVD','American Crime â€“ Movie'),
('ù,ü‡rÌDü“7Íõ¼†','feature','DVD','After the Sunset â€“ Movie'),
('ú6àÿA]H}•dÜ”oŒ','feature','DVD','A History of Violence â€“ Movie'),
('ú„ÁqñÃJµn[b¡¦ï','feature','DVD','John Tucker Must Die â€“ Movie'),
('û—Ãµ6K™Ì˜^”}T','feature','DVD','Che: Argentineren â€“ Movie'),
('ûë‡¬šCæ‰T.¤ñYÊ','feature','DVD','Che: 2.del: Geriljalederen â€“ Movie'),
('üéC>šEÓÉ6³‚\\n','feature','DVD','Buenos Aires 1977 â€“ Movie'),
('şg›ùO`µ+âµpU`û','feature','DVD','Lucky you â€“ Movie');
/*!40000 ALTER TABLE `disc` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `physical_collection`
--

DROP TABLE IF EXISTS `physical_collection`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `physical_collection` (
  `collection_id` binary(16) NOT NULL,
  `barcode` varchar(13) DEFAULT NULL,
  `format` text DEFAULT NULL,
  `box_set_barcode` varchar(13) DEFAULT NULL,
  PRIMARY KEY (`collection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `physical_collection`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `physical_collection` WRITE;
/*!40000 ALTER TABLE `physical_collection` DISABLE KEYS */;
INSERT INTO `physical_collection` VALUES
('\0®ßOÑ³öi¡ÉÊ›','5706122383186','DVD',NULL),
('\0rßv@À\n V°Gª^','7321940015231','DVD',NULL),
('\0”DÔiLö‹¶°-0­é¡','5051895031674','DVD',NULL),
('\0âÕ]ccJH¬×ëf(Îxğ','7393805004469','DVD',NULL),
('#‚*øIÖ²sê§â‡‚','5050582408362','DVD',NULL),
('{şİ®G¤‚~§ÕÇåï','5050582706604','DVD',NULL),
('îºÓ­¸MY¹š)ØašK','7036988002197','DVD',NULL),
('UŞ«øFj¢­Ù,ã&Ò\r','5051161245514','DVD',NULL),
('õBKÁ8I²àù[ÿW','7036988000056','DVD',NULL),
('ŸåMCA¥€X_««Xj','5706122347423','DVD',NULL),
('4Ÿòè@¦´¥wdHL','5051161247716','DVD',NULL),
('Û“ÛÕN>²™Ó\'É•(Ç','7036988023802','DVD',NULL),
('áU±fBN«\'~®ÁÖ}‰','5050582606430','DVD',NULL),
('æì.O·­`Y-Jæ1g','5706122385890','DVD',NULL),
('	3Ñ,Ì„O£8ŠÕ“PX','7046685002758','DVD',NULL),
('<G\\«WNó¢¬ºUıæé','5708758677981','DVD',NULL),
('ÛONkK÷¿@B­Ù@[','5050582427912','DVD',NULL),
('òß›™M¬…8ƒayhˆ—','5035822314139','DVD',NULL),
('\rĞzvàB}¥ºĞ-Mr£','7041271782038','DVD',NULL),
('\rhÅ;Kù®s}oWÙ','7321940162898','DVD',NULL),
('\r€˜¢ÑÙL…—\'ÛP3â‚','7312065000158','DVD',NULL),
('\r¯$u5×Aí¯@b\'ŞÒ','5050582070583','DVD',NULL),
('\råO¶çC£‰iÉĞğE¹W','7036988010123','DVD',NULL),
('i°°E,şúÇÄN\r','7321940185842','DVD',NULL),
('óâˆHŸ”Ô\n¢¥¢:+','5706122353363','DVD',NULL),
('\\¯Ù8BI¶…z~ØôL','5050582045796','DVD',NULL),
('p(g3fD…­‚±\"8Ÿn','7393834099801','DVD',NULL),
('s°+WFa¡j\"ŒïAÅ','5050582445350','DVD',NULL),
('úJñ·KC©Ğ›T½Ì ','7321940015477','DVD',NULL),
('ûƒÚÒrF–ÒÄ«@#õ','7036988008489','DVD',NULL),
('acRğMÖ”4¼)ù–Uù','7321979318471','DVD','7321979337748'),
('N²—Ÿ5K¬§«VBÈ','5051161109311','DVD',NULL),
('øVÀ‰jB„=ÑĞ\0ô','5050582167986','DVD',NULL),
('–ê	PBA0”¿¿yÕ¯$M','7036988000889','DVD',NULL),
('ü¬tkøAÚ‹ÆP¤‡>ø','5708758652643','DVD',NULL),
('øåºEV‰\"\\µù$iJ','5703976149029','DVD',NULL),
('ÑV²´Fš¢[5ÏbF¹','7036988017719','DVD',NULL),
(')7váKÔ”BuN£-Å¦','7036988014572','DVD',NULL),
('f‹?ZN[µO^B’wqµ','7321940211220','DVD',NULL),
('lÙ¸ôóFSŸ¡¿ B¢','7332431033085','DVD',NULL),
('ÕñÚ„L·½ÍÉ8{8¶','5051161279311','DVD',NULL),
('š¿ÂLKèºY_^Ä¥ü','7393834503407','DVD',NULL),
('¦ƒADü¦e0>¶Ò','7036988016163','DVD',NULL),
('vò\"”]N© >°3FEƒá','7041271717832','DVD',NULL),
('â¾3EM+¤qÒw<Djâ','7393834136100','DVD',NULL),
('ş@E¨ææúçtî','5050582117318','DVD',NULL),
('›‹«MH­4:÷¹Sá','7321940701387','DVD',NULL),
('ÓävmoOé¤„òÒ…Y›','5051161102916','DVD',NULL),
('â|¨eBƒ›ãG9™wp','7036988019645','DVD',NULL),
('è˜QZàEr¥Ù†ñ•¹','5050582252453','DVD',NULL),
('tå2OKš–ƒ%F','7036988029729','DVD',NULL),
('u5’¾óI!šO£	»Jç','5708758659604','DVD',NULL),
('u×EÿLBi“W÷‚G.C','7041271359339','DVD',NULL),
('\\ŠØ±ƒD©¯­­èKôü]','5050582155617','DVD',NULL),
(' Ç¦Ô.Kî¼²ñï¤¬','5050582033472','DVD',NULL),
('!û€ë•NC…€a°Ü','7036988007307','DVD',NULL),
('!ë­GPßJÆ©Ûå[GêRí','5706141746887','DVD',NULL),
('!ïEébC¼˜õ{ahıêÆ','5051161229415','DVD',NULL),
('\"G¼w N–¥+:Óä_Pá','5051161104118','DVD',NULL),
('\"u@G\"”¯Óí®­K','5050582126242','DVD',NULL),
('\"©^ªYG‡ã„ªu','7393834248001','DVD',NULL),
('#_ğşÅ½C¡¶Ğrå[$','5050582707526','DVD',NULL),
('#d¤cÒ HªŠOoÏjùë','7090001712296','DVD',NULL),
('$êOW¯!úğ[jš¾','7090001711572','DVD',NULL),
('$8Ôp¬LÊ½Êùßƒêûé','7036988019102','DVD',NULL),
('$ú¬‰â*J6>_VA íJ','7041271703736','DVD',NULL),
('%Ô!ÑO·©tZ f8dö','5050582723038','DVD',NULL),
('%3¬zyaL˜¶b<§ÃÎTl','7036988018549','DVD',NULL),
('%Lf™–.Bi‹P((¯d÷','5051161127513','DVD',NULL),
('%gÈèfD‹¨\'üÂ¾„#','5706122350560','DVD',NULL),
('%É¥UÖNıºá>¦ ¼¹','5706141750969','DVD',NULL),
('(«6,UDí¦`2ŒŞãk','7321979319720','DVD','7321979337748'),
('(ØšúÇXI³êòÄÓØ','5050582130089','DVD',NULL),
(')3ƒl‡6ON¶tB(bBß','8717418167745','DVD',NULL),
(')«£ŒİG©”+E8¬m\np','7036988005327','DVD',NULL),
(')Àµ9~ßM³²Üµx»ÒP(','7036988027305','DVD',NULL),
('*J˜5ÉçG¬‹vSıÑëÀô','7321940314563','DVD',NULL),
('+\Zy`óÜAÙ·¶Æä•C¤','7041271650238','DVD',NULL),
('+*Ü2wˆJ4!ë“P’','7321940160757','DVD',NULL),
(',ºÌ8HMh”\\Û#—{ö','5706122359693','DVD',NULL),
(',a\Zy±ÌC[µš³Ö: 	:','7041271289155','DVD',NULL),
(',ƒ^oDÕM¾õçš\\è','7036988020719','DVD',NULL),
('-X„p“ÜI\n¹rŒ3”$Z','7036988023864','DVD',NULL),
('.·­çï–LÍ¾Ëº´°±2','7332431033696','DVD',NULL),
('.ÀVÜ\ZJ/¤XP\0•-Ó','5051161263617','DVD',NULL),
('/W;jo_Aƒœù•§(r','7036988005525','DVD',NULL),
('/ør`“\'EE·èÕûnİ¬!','7321940219622','DVD',NULL),
('0bF»×»NñÕFó¬ªŞ','7321940019994','DVD',NULL),
('0•…øÕK´œ+>múp','7041271352750','DVD',NULL),
('0Ó\"=§yL-¦³ñ‘ ','7090001716065','DVD',NULL),
('2Ó£\0]DlŒ¸Ï÷q','7321940763736','DVD',NULL),
('3~CƒJUŒ6G¥Àia','5706141744715','DVD',NULL),
('3ÔY8ª}F¶Šfª“Rç´9','8717418070014','DVD',NULL),
('3Ø\r„ºzF”wl£~àÿ',NULL,'DVD','7041271973931'),
('4{œ©ê.Lˆ¦Á~F)âÅ','7393805089169','DVD',NULL),
('4°á®3xCÆ²Ï>i°Í–','5706122361542','DVD',NULL),
('5l]ÉTROd¡sCG’°6','7090001729485','DVD',NULL),
('6ÚÖAâëL^@fi‹dá','5050582192681','DVD',NULL),
('8/§€gK¼7üåæœ°','7036988010819','DVD',NULL),
('8R#«”•A˜†M¢:Ã¸e','7321940163208','DVD',NULL),
('9 ¦oŠDO²S½Ê/¯Ëæ','7036988022836','DVD',NULL),
('9F~º–OÓ—ùo²>mÍ','7070022867083','DVD',NULL),
(';4è‹†ÆI¥?O]‚ûİ','7393805220463','DVD',NULL),
('<5	>ëpL¹ŠÇG½{¥','5051161261019','DVD',NULL),
('<¾çgËMæ©÷³µ’\"u×','7321940018317','DVD',NULL),
('<ë2û¡N ù3ÀµŸ','5708758652971','DVD',NULL),
('=9–åMã¯¡XƒSÕ±h','7041271443731','DVD',NULL),
('=§\'OB•s—şGMô','5051161207918','DVD',NULL),
('=;ÃßD)–\\Ç»°…J','5706122395530','DVD',NULL),
('=ÀùL6•êŠâãe¬>','7036988015333','DVD',NULL),
('=â¶ ¾LI¡	oÔL.','8717418071059','DVD',NULL),
('>Dr\"^ËF«¤zqNŸh','7036988014497','DVD',NULL),
('>ì,¤”K¸¨ÕZÒÊ¼','5706550031499','DVD',NULL),
('?$#êH|˜1¨?®á','7332431013759','DVD',NULL),
('?D¿Ù*G’F\0ª8u','7393834442300','DVD',NULL),
('?á¦“ç“LÇ´|ƒ#©V9','7036988027053','DVD',NULL),
('@†æw£E¡§\"qM]@4 ','7332431034365','DVD',NULL),
('AçpÀ”O%¿ˆ­ÿuöÁ\Z','7071400013207','DVD',NULL),
('B%±ô»Eè™HÓWğy','7090001711633','DVD',NULL),
('B5;ŒÓ\\H.´\ZnùmføX','7321940190006','DVD',NULL),
('BYK7\'ŞEhšOo³û«”','5706122361436','DVD',NULL),
('E@[ÓH‰‚\"iÛ†ƒ’','5050583028064','DVD',NULL),
('F+µwÿıL¸ºL¿O~É³','7332504002291','DVD',NULL),
('FcãÚXNY¶t C­%','5050582557091','DVD',NULL),
('G_ÒÂUÙBÏ¡^Íz¡YÚõ','7319980079757','DVD',NULL),
('HÈ«¹ÔKÄ¦ËÿQÿ¦ğ0','7393834361601','DVD',NULL),
('HÜD‡îLç¨ ¡àÍÂÒ','7321940129891','DVD',NULL),
('I	/ÿ¢.F­¼xö\r Ñwğ','7332431031920','DVD',NULL),
('IZ—÷¨ñNÙ•Şeé )À','7090001727542','DVD',NULL),
('I•¦TğEªÅWóa!Ü’','7041271513939','DVD',NULL),
('IíÅÆØDÅ¸n%c­ha','5050070006476','DVD',NULL),
('K\'ÁÀ„K‰=ñÓ4³','5706126392856','DVD',NULL),
('LJ&™¿.N_µ[ÃQæÖ°ë','5050582009224','DVD',NULL),
('L |®@ƒâú8F@gÃ','7071400016802','DVD',NULL),
('Mkc.\rïJl–®`é;“','7332431017290','DVD',NULL),
('Nƒ(Cá¿5›šqÀ¸ß','7041271501158','DVD',NULL),
('NŠµ®Ò…B[¨l:ˆ+ê•','7036988015180','DVD',NULL),
('OóàmÓèHÑ‰ø¼*9ŸÉ','3259190694927','DVD',NULL),
('PPUÉàA«xZ>½:\Z','7321940017303','DVD',NULL),
('PÀõ-II±zÃ<y‰Í','7332431013360','DVD',NULL),
('Qe¹¥A=š\"¤õ·U”','7321940016603','DVD',NULL),
('Rçš\Z¶dHiŒ\0öÂPã','7036988000872','DVD',NULL),
('S/JÔcˆEV–¦É­{','7036988010918','DVD',NULL),
('SSÊ—8>Iµó\"û5¤›','7321940763750','DVD',NULL),
('TygG¼I°6€ÓÑX','5706141752611','DVD',NULL),
('TÚDÇ6|JcºÕKë¥‘àg','7090001718243','DVD',NULL),
('Vj­îHV¾Ó9iiù','7321940025087','DVD',NULL),
('VšÃùĞÃ@¥«ÔÎ:ã','5039036000284','DVD',NULL),
('XQ² MAzª·£Wş„','7070022005270','DVD',NULL),
('XfîÍåH™¶8Ó@¹À”','8717418183998','DVD',NULL),
('X¹/›ìFK3¥Ï0›ÔZK','7321941345733','DVD',NULL),
('Xş°æ=\nOÛ’ï/ĞbiÒ\'','5708758652674','DVD',NULL),
('YÛØ]6¨M½¹¥ÕóPö`','7036988009509','DVD',NULL),
('[÷	£¾G)òÂ#*œ ','5035822451292','DVD',NULL),
('\\yÓO)@4ªüG‚Ğ‡‡«','7036988000346','DVD',NULL),
('\\i Á ¹@\Zš? ¼\r¯†','7036988021334','DVD',NULL),
('\\¶ñ5ÉA®²³DkÚÅ©¬','5051161187913','DVD',NULL),
(']d-ñ<9KÄ«µHºú«à','7321979318488','DVD','7321979337748'),
('^àƒ,sNø¹ÖóHQÏ','7041271352354','DVD',NULL),
('__Ş“JK°½Oíå+','7041271459954','DVD',NULL),
('_¿µJçHG´a”=ÓLù)','5050582343250','DVD',NULL),
('_îuêqéFÚ†JÆS)\"‹Û','5706127498601','DVD',NULL),
('`	¤€êîB²†hsóÁÈ','5706122358979','DVD',NULL),
('`¡p_8Gÿ«Ç~kÃZ/','5050582552386','DVD',NULL),
('`FÉÓãÑN¿şøR^Ó','7036988002708','DVD',NULL),
('`½{]çGG®ê8^«€(”','5708758673860','DVD',NULL),
('`İ_AõB‘ÙëbŒgO±','5051161112410','DVD',NULL),
('`ú+µãÖH‹ÜXø{ş‰','7041271628435','DVD',NULL),
('a5ôï©ÎAS‹p™>É6$','7041271702838','DVD',NULL),
('a˜›4ÿN¦«ıˆ¶ÿM','5706141747181','DVD',NULL),
('aÀ6\\3AX«-Ü;¦êx','5706122363935','DVD',NULL),
('b‰£= ÁAø‡ôŞ=6¸_','5017188883368','DVD',NULL),
('c?\0¾K HÕ4ˆêÆ',NULL,'DVD','5050585603873'),
('cW°¡ùÂNç©‡ù_-é','7041271575036','DVD',NULL),
('c‘FtVJ‰Ã@s‰','7321940701417','DVD',NULL),
('cŸBğsEâ‚x®Ó»æ2û','7321940132334','DVD',NULL),
('d\\\ZG@í’	‘š\"€~','7041271137753','DVD',NULL),
('e¶\\^¹GÒµ`t}k4™','7090001799778','DVD',NULL),
('fRšÎoJÜ©\n?lÑA','7393805635762','DVD',NULL),
('fSip¸I%»db','5050582552621','DVD',NULL),
('gj#Ó\'vMƒ™j}sİ£0','7070022870571','DVD',NULL),
('gxí½C{I §±ˆw ','5017188889322','DVD',NULL),
('g}.À«Dï„ĞÛ\"ã¢%','5708758678353','DVD',NULL),
('hƒV JÄºÊ§›×','5035822728172','DVD',NULL),
('i¸|oî@é‡væ+ª5','7036988003668','DVD',NULL),
('k`¶¢ÕD‚.úùƒ@U','7036988012660','DVD',NULL),
('k™Ş%r÷G+õ@¡©x','7332431013544','DVD',NULL),
('l:°ªt¬NİªO3òX”‚Y','7041271429131','DVD',NULL),
('n,ñzëíB#«`ı=¤','7393805418969','DVD',NULL),
('oˆ=¨{êJ¿‘9tÈok™à','7036988023949','DVD',NULL),
('pKº÷ŞDø­Ø¡ÚŞI','5706141732545','DVD',NULL),
('q\\¹oK­h\rí»Øz','7070022021140','DVD',NULL),
('qV«(øN—ŸK[c–*~','5051895002025','DVD',NULL),
('rÙ€F¬ øÖˆ¾ã','678149094893','DVD',NULL),
('rêŞhB¢¯ÁÃ¡ L','7036988010314','DVD',NULL),
('s@t¶\"F—³àv¹—G','7321940717302','DVD',NULL),
('t çO;kMH¯œê<ìæ†','5706122387313','DVD',NULL),
('t@ıDÈäIª€Ø|Ë','5706122362259','DVD',NULL),
('uI‰×,D?‹†İL/¼H','7321940593401','DVD',NULL),
('uËèÊåNò¹-([È\0›o','5708758684668','DVD',NULL),
('v5½\rGÜ„öâeø','7036988026285','DVD',NULL),
('vG·TVMD…?ÓóåÇ”','7036988007000','DVD',NULL),
('v’×Ù,@»ºre1‰^O','7391772322333','DVD',NULL),
('y…à°Ds¬°Æ¸»×À','7090001727665','DVD',NULL),
('{¢$ö0ÄIè‡ú!Ğ\"È\'','7321940017532','DVD',NULL),
('}t8ÆÿğLª0pQXvgD','7041271470836','DVD',NULL),
('}£ÀÅ,B7šl\\Ì‚ÅX-','5706122357842','DVD',NULL),
('©5ŸÇKˆ·¸ÌÉõ€ş„','7321940736723','DVD',NULL),
('€7ÇáòvJÕ„g½šD','5050582357660','DVD',NULL),
('€—¢`cM\0»©½8Km','7393834524204','DVD',NULL),
('ŞC»®c)Ä]ƒÓ','7332431012387','DVD',NULL),
('¥ø‘ëÏOúl¢ÎÂ‚','7070022874852','DVD',NULL),
('„ï%±á’F¹}‰3¶z#','7393805777660','DVD',NULL),
('…B¢™\\	A2´Å¦‰×>ı','7041271806635','DVD',NULL),
('…\rKÀ¼‰Çütãl','5706122398661','DVD',NULL),
('…å¼jiãOÁ„ı,¦Ñã','7321979318440','DVD','7321979337748'),
('†!k‘«H×ˆã!DÂEo','7036988023765','DVD',NULL),
('†à?4IÌ˜ =®*pt','7041271039859','DVD',NULL),
('‡r±Ÿ.oDïƒ§ªXpşÎx','7332431014602','DVD',NULL),
('‡â™9kåIÀ»5õC…ÂÖ™','5706141743268','DVD',NULL),
('ˆ%[u»G ºØ¢V','5051161243619','DVD',NULL),
('ˆ­ä˜Y‡K!¦s`î=6¡o','7041270931352','DVD',NULL),
('‰1rà\n(Mş­6ëíï¶óÃ','7393834457304','DVD',NULL),
('‰ÜâN¶Mêªéèêİô9T','5050582164015','DVD',NULL),
('‹!r°/NqŠ3Ä:æN','5706122367452','DVD',NULL),
('‹IÎÈ¯JÅ“æ¿ÇR2eË','5050070000313','DVD',NULL),
('ÌÛä—N„‡!bw^','7321940015187','DVD',NULL),
('à¯IÇúIËÍªºµS…Á','7036988013285','DVD',NULL),
('õ÷¤t½O£ø7nã”ê','5050582445251','DVD',NULL),
('È¢» Fšf·r\'“\".','7041271780737','DVD',NULL),
('jJÆO,¨%­^(¡®','7047271539832','DVD',NULL),
('dáíO%Oç•º%èŸY—Ê','7036988012257','DVD',NULL),
('§Ø\Z–Ä@)šÂ Có¦Nì','7071400054163','DVD',NULL),
('åÊíÁNµ‚õQ``K*s','8717418220631','DVD',NULL),
('SJí•%Aş›z&È…Îß¼','7036988008410','DVD',NULL),
('”6\'fF’•5ˆÖÓÕr©','5050582150599','DVD',NULL),
('•<­ŸhOËºnª		`','7036988015807','DVD',NULL),
('–`zP»Eò¯·n§QÌù`','5051895018347','DVD',NULL),
('–À¡î¡•EIóÁÑ Mvh','5050582063318','DVD',NULL),
('–ÊxO¥Gêfp6³','5708758654784','DVD',NULL),
('–â4ÄÿH±’®¹pİk#x','7321940807720','DVD',NULL),
('—»RéE­–ãŒ€°','5051161190616','DVD',NULL),
('—l«²/G¢³ÙE5nA',NULL,'DVD','7041271286857'),
('™`æ÷2I‘¾;)Ár¤','7041271443632','DVD',NULL),
('™…Ğï=&A‚E©sôŒT','7321940007489','DVD',NULL),
('š+[dí¤@†­Ll°3d9','5051161181515','DVD',NULL),
('šrçïì¬B.œ˜³{¹–(','5039036001083','DVD',NULL),
('šÍÌú\"¡LL‡ş9l\r','7321940162904','DVD',NULL),
('›K!ì!4A¿¥`Ü‘hç','7041271817334','DVD',NULL),
('œ6g¾ÃB£^aÈ¯§c','5706122358993','DVD',NULL),
('œ…H«I|K4)³‰e0¤','5706122385807','DVD',NULL),
('œ–†ÏsBÌ¹d[ónµñ‚','7393805456268','DVD',NULL),
('pg×ò7GÏšOÅÈV­ç','7041271460059','DVD',NULL),
('ä÷q57Fw úËV®\'6','7090001726408','DVD',NULL),
('¡¤w“:|OX†ØZe(÷','7393805004360','DVD',NULL),
('¢Åx­BOœ¸H€˜aÿd','5050582522273','DVD',NULL),
('¢ü³³©/D×ˆÿïKt‘À','5706122383230','DVD',NULL),
('£?ôøØåKê¸\n=h~]Å','7391772322340','DVD',NULL),
('£ÔmM¸If‚ÇÆ†´Ë$>',NULL,'DVD','7332431007321'),
('¦[¸\nOßÉCFñ=ï','7036988011793','DVD',NULL),
('¦ÔJêVFf…`\nÑœã†©','7036988002227','DVD',NULL),
('§I.éå’G;¤›ĞËY@','7041271497932','DVD',NULL),
('§ŞÅ‹…Nòƒõ[8I9ò','8717418066123','DVD',NULL),
('¨(3­w°FÛ¡³qÜiÒ²˜','7036988004825','DVD',NULL),
('¨q¤-p\"J¾»²Û²Öû','8717418113094','DVD',NULL),
('¨a•0ÀJ|•œø^9p…ë','5706122395509','DVD',NULL),
('¨°R ¨•IŒšØa:­&K','7393834261802','DVD',NULL),
('©Ÿ3[úm@t®Rc^x\0ÁE','7036988002487','DVD',NULL),
('ªÀ³5iıK^°â¬7Amã','7036988012226','DVD',NULL),
('¬$ĞÒªOc£\rÍ,#Ãíı','7036988016323','DVD',NULL),
('¬oa’ú®HI¨ş˜ƒ$ıâÏ','5050582089448','DVD',NULL),
('¬Ñ´\'•KÀ…¶@öC}ˆ','5050582346695','DVD',NULL),
('­»hOš‹Gz¯½ßºÎ\0ê','5051895002575','DVD',NULL),
('®*ÿ.‡ØNúš¯Õg^Ë%~','5050582333800','DVD',NULL),
('¯,»LyLxší:Ğ©oò{','7036988001244','DVD',NULL),
('°ŒöåN	‰FÃÚ?t3','7041271579430','DVD',NULL),
('±–}[8K8Ÿf3ìÅ','7041271428332','DVD',NULL),
('±ÛLTM¨¶ïŒô ¼(','7036988003613','DVD',NULL),
('±ªÈå2rJW”¹@Ó?cÍ','7041270837050','DVD',NULL),
('²^Å©ñIb’Õc8,»','7041271289759','DVD',NULL),
('³YpóL?˜™7•x`B ','7041271648136','DVD',NULL),
('³§oı‚+N/‰*ìŸºŸÇ','7036988012653','DVD',NULL),
('³ş´VppH‹ƒ‰å/´tJ','7090001712593','DVD',NULL),
('´84$‹¬Eí”äÜ‘a_#','7393805009754','DVD',NULL),
('´\\ö7ŸNg–¤ÔÙààÒ','5050582125603','DVD',NULL),
('µçÄ!á#MR²z@¿ÅL','7090001712852','DVD',NULL),
('¶Øb+qKcŒêF¿Cªë','7332431029538','DVD',NULL),
('·ìá¼\ZC–ƒ¯u\\$~','5050582466669','DVD',NULL),
('¸{T¨@H÷“Şé’Œm7^','5014437836137','DVD',NULL),
('¹¶jºFS©^ÅJ\"?8)','7036988031258','DVD',NULL),
('¹é†<œpK‹¿ÚÉá·	','5706122362853','DVD',NULL),
('¹õıB1C(›Sc¶+{','7041271386618','DVD',NULL),
('º–Ø?KN³Èø:õÁ','7321940830452','DVD',NULL),
('»*J?ƒhEí‹a%nŞ‹íd','5030370905631','DVD',NULL),
('»ug@Hv¹ÿ[aÃ&Ì','5050582375503','DVD',NULL),
('»â¸á·±B7·ê9:¸:¤K','7070022870830','DVD',NULL),
('¼D¾~ôA@£|Í®Êì4ò','7041271817532','DVD',NULL),
('¼•MÆ†:N,£oWğ`àæÇ',NULL,'DVD','7321979337748'),
('¾ÎÂ˜÷hF>§‹Á•.ZØ<','7090001726163','DVD',NULL),
('¾óŠ?rÓK®à½RV)1©','7036988012233','DVD',NULL),
('¿Ñ>¹IÏ¥°”Æ5','7393834509300','DVD',NULL),
('¿àÉnƒ8B\n‹-Ÿ¡¥v2.','7332431018518','DVD',NULL),
('¿ê¾ü%^D‡£™·[ø_eÑ','7393834265107','DVD',NULL),
('Áhm\0VXDš¼TåÏñJ','3259190307094','DVD',NULL),
('ÃÑİÓ5@ß©|¥ÍCnı','7036988026308','DVD',NULL),
('ÄtË²-JG–×¿•&Ò4','5050582084887','DVD',NULL),
('Ä‘e·æH0ŸñxegÈwÁ','3259190304697','DVD',NULL),
('Å7ÑPpA&»OHOˆ­','8717418116293','DVD',NULL),
('Åe[g,9GœºuÒ¿M·q','7332431010963','DVD',NULL),
('Åë8:_DI YGş“f','5035822176133','DVD',NULL),
('Æ°«Í´ÑOD©úñ3Kdöd','5706122384138','DVD',NULL),
('Çñ&8ş˜BÌæÍ·>#œ','7090001711565','DVD',NULL),
('ÈÖ³‚ô)Eõ¿fy[¦€','7041271288653','DVD',NULL),
('Ë}™ŞÕCÇ™Ï\ZM','7041271139658','DVD',NULL),
('Ë}ÃMšŸH˜©h\'ÁÔ<.À','7036988015890','DVD',NULL),
('ÌèÉV–D\rŠ5R›\'ë','7090001711428','DVD',NULL),
('Ì”ÃÏ<¹JŸ®zHr%0Š','7036988002586','DVD',NULL),
('ÌÛdgÏI@\Z xxåmZğ','3259190304994','DVD',NULL),
('Í.ÃŒÎÚJ?ƒYúØÌ²…î','7041271539939','DVD',NULL),
('Í­2»ºN §Rš\ZyÃ”','7090001712319','DVD',NULL),
('Íš‘ÜçSD\'Æ:sûÃzÖ','7319980077517','DVD',NULL),
('Î¨o¨O[°ß^9û[»§','5706122366714','DVD',NULL),
('ÎfÔp?@Ñ¸à‰YG¡','7321940189970','DVD',NULL),
('Ğ‚\"FõM,ŸÎİæ…¿*p','7041271719034','DVD',NULL),
('ĞÀ¯4À¶AÉ‹?ÀÑ©\r','5706122395493','DVD',NULL),
('ĞŞì?ŸrKÃ®×D„øÜØL','7332431029774','DVD',NULL),
('ÑªÜ9¡Ml‚,Jeå/Ã','7332431031265','DVD',NULL),
('Ñã)(\ZJº˜îÓ6¯\0','3259190353411','DVD',NULL),
('ÒócÎ®äI­²³#y˜h\nï','5050582005431','DVD',NULL),
('Ó,rWîOrº¿Äëß£š','8717418105877','DVD',NULL),
('ÓJûvEaƒõ‚ÅI\'','5050582558470','DVD',NULL),
('ÓÏ¼iá“Mµ³[	²„Şk','7036988018532','DVD',NULL),
('ÓàíäcêNB†\"!ÖŠÿ','7036988012240','DVD',NULL),
('ÕGé?G¹š•\nßdîY5','7332431032170','DVD',NULL),
('Õ¥ *iéL\Z¬Ñœ1õV','7036988022140','DVD',NULL),
('Öµ%¿.K¡”6ß®L','7393805777165','DVD',NULL),
('×`Ä¢J“°’\"Vş@_','8717418140878','DVD',NULL),
('×Ì~½°ÈIä£Á¨P(\"£Ö','7090001726903','DVD',NULL),
('×öph˜K‘a­ÆI¿å©',NULL,'DVD','7036988020283'),
('Ø¨wõL+B0ƒyÒ¯=¦,','7036988015142','DVD',NULL),
('Ù·d 2O®é¾¢ÄŞğ','7036988002296','DVD',NULL),
('ÙÒ5£sIí²0™ù¿Ro','7036988023710','DVD',NULL),
('ÚEÚ¤v†B§ŒgÍÄ\0ğt(','5706141747020','DVD',NULL),
('ÛSG”¿Ç£Iƒ…Ù','5050582390520','DVD',NULL),
('Ûg›Î”çD#¬÷öÙ[\'v','7332504002208','DVD',NULL),
('Û›Kì;•BÆ€ù#Ãò”','7041271524638','DVD',NULL),
('Û›M¡XM¯ÀÃBê','5050582195958','DVD',NULL),
('ÛğÊ­¹eF™œLI<«£€é','7321940012995','DVD',NULL),
('İ#ĞJiHv©×Ï\"~v','7046685002123','DVD',NULL),
('İ(j£IIˆ¯¤÷º>øs','7393805777363','DVD',NULL),
('İyZ¯ußBŞŒ2\r%êå','7321940162911','DVD',NULL),
('Ş<z—QaAğ¤!)ä0í=1','7036988022133','DVD',NULL),
('ß+ígÌuK£¹Ìê3ØlÙ','5051161166512','DVD',NULL),
('ßX•‡0™NøŸ” Y©5','7321979318457','DVD','7321979337748'),
('ßAĞ@õ§œ—v÷!‚','7036988013605','DVD',NULL),
('à¹g›L)M³Óºåşß','7041271322456','DVD',NULL),
('àîÕÿCBES¯OvGü0Ò','5706122388921','DVD',NULL),
('â¯¾ĞFYˆÍää§’\r','7332504001980','DVD',NULL),
('âSæ5†ûD`Œ2äå³(T','7321979318433','DVD','7321979337748'),
('â®;«¼DÛ¿c%ÍôÖ…','7321942294528','DVD',NULL),
('ä­i[½O¢ söÄgDãZ','7090001727917','DVD',NULL),
('åN†jXSKÖœhéó¸÷','7321940017273','DVD',NULL),
('åÁ\0n[F–t‹d‡U.','5050582200034','DVD',NULL),
('åÁ¡µÏfI¥_u³ Ü6','7393834126903','DVD',NULL),
('çqtümH®†3½oÔöá','5051895012543','DVD',NULL),
('è\\¶èTÁC>†›FT„ãQ','7036988013803','DVD',NULL),
('èƒMÆHÓÚ¼\\¡^Ô','7036988014220','DVD',NULL),
('éˆÎ…öIAe¢•lKŞ','7070022870847','DVD',NULL),
('éÇnŸä¡H°¯ãÀ?Ã‰äs','7041270926358','DVD',NULL),
('êÍAÒ\'NF˜„Œ%­ÔŒK','7332431015494','DVD',NULL),
('ë²¥‹N¸©³2¬—‹?i','5706122389416','DVD',NULL),
('ìOË¶ãF}´>Äƒoï@','7321940017396','DVD',NULL),
('ìsX‰¿\0N™¤N=¡ì\\ôa','7041271371959','DVD',NULL),
('íaˆÜôÕK÷œ‡vìÜÏ5ì','7393805005824','DVD',NULL),
('îB_j€ME£ŠˆÖ*ƒS','5017188883788','DVD',NULL),
('î¬.Ù†Ø@i¶eÃ„ıE','5050582325980','DVD',NULL),
('îç¬4xD“c3ÄDl™','7321940015965','DVD',NULL),
('ïŠ¥åÂÕKƒŸß÷\n°s6','8717418103385','DVD',NULL),
('ñ|äÌ•ÀM–IMÂí¿Î£','7321940018126','DVD',NULL),
('òtÄRæ›Hç½9ÓóMrA','5050582766004','DVD',NULL),
('ó<n¾iLs–ÎlîUµ)C','7090001712678','DVD',NULL),
('óÓŒÙ<ÄB»½›ü6¤´\'U','7070022867168','DVD',NULL),
('ô\ZãèA@†CSìéÁâÈ',NULL,'DVD','7036988023260'),
('ô\\†\0Os‡%\0$\r\0','3259190318694','DVD',NULL),
('õ\"%h¡E¹¬ä@ZØyÈ‰','7046685005100','DVD',NULL),
('õb]gF^¦ºám\\~İÒ','5050582700756','DVD',NULL),
('õ´„£n—GÊŸíÂ+/ú¡','5051161248911','DVD',NULL),
('öáÙ?P;@À¶|A4ßv“','7321940031606','DVD',NULL),
('øİ¾­uMİƒÙ‡Mè<mú','7321940017211','DVD',NULL),
('ù(UÖ(úI8ŠÂ®ÒÀ÷÷','7041271372635','DVD',NULL),
('ù×è1ÈHVZîrX!','5706141744869','DVD',NULL),
('ú*ÒœØNG‡-ä»µÖ*=','7036988006331','DVD',NULL),
('ú/Š\ZŒ5Fƒ›A`û}û','3259190253094','DVD',NULL),
('úO\Z”ÔJ¹¡3\'În®Y','7041271338051','DVD',NULL),
('ú…\'•wE*›G{2Vl','7393805648861','DVD',NULL),
('úßíB›pH³İ¤‰ƒÊ)','7046685002130','DVD',NULL),
('û1V¾]GÉGÅ/\n.UÕ','7321979318464','DVD','7321979337748'),
('üÑç¼M\nŠd¤ø×X','678149098129','DVD',NULL),
('ü^cw’A¨¬2fË','7036988000179','DVD',NULL),
('ıkœNÃQLÍŠE^wÇ','5050582129922','DVD',NULL),
('ıvàÃçO¾„¨­÷û÷\Zò','7041271371256','DVD',NULL),
('ı‹úŒI@EA­^ê›§tX','7321940169545','DVD',NULL),
('ş/Éƒ„Fz¦\"šú(»','5706141747013','DVD',NULL),
('şn D#G®0è	Åv','7321900954990','DVD',NULL),
('şÉ{µ”îH\rÿYt±¶—Ÿ','7036988016279','DVD',NULL),
('şÏ¾²h@C¬à áÍÑÌ','7393834136308','DVD',NULL),
('ÿâõàÚÀDn¯Şcw5Êb¡','7321942294597','DVD',NULL);
/*!40000 ALTER TABLE `physical_collection` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `content_in_physical_collection`
--

DROP TABLE IF EXISTS `content_in_physical_collection`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `content_in_physical_collection` (
  `collection_id` binary(16) NOT NULL,
  `content_id` binary(16) NOT NULL,
  `box_set_title_sort` int(11) DEFAULT NULL,
  PRIMARY KEY (`collection_id`,`content_id`) USING BTREE,
  UNIQUE KEY `uk_collection_box_set_title_sort` (`collection_id`,`box_set_title_sort`) USING BTREE,
  KEY `idx_cipc__content_id` (`content_id`) USING BTREE,
  CONSTRAINT `fk_cipc__content` FOREIGN KEY (`content_id`) REFERENCES `content` (`content_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cipc__physical_collection` FOREIGN KEY (`collection_id`) REFERENCES `physical_collection` (`collection_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_danish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `content_in_physical_collection`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `content_in_physical_collection` WRITE;
/*!40000 ALTER TABLE `content_in_physical_collection` DISABLE KEYS */;
INSERT INTO `content_in_physical_collection` VALUES
('\0®ßOÑ³öi¡ÉÊ›','–ÃíÉL>Oú¤Tœ½‘',1),
('\0rßv@À\n V°Gª^','Ëu/cX\ZE7«¾Ü×…H',1),
('\0”DÔiLö‹¶°-0­é¡','è·½DH| Ê8idËù',1),
('\0âÕ]ccJH¬×ëf(Îxğ','w¿„_ÈD¤©,*tlN|',1),
('#‚*øIÖ²sê§â‡‚','Xçè·öOé…ÇkÃÄˆn',1),
('{şİ®G¤‚~§ÕÇåï','â¯ôÖçÍHæ„^“°tÒ',1),
('îºÓ­¸MY¹š)ØašK','Â_ÑÃ+*BIº*6~I±5',1),
('UŞ«øFj¢­Ù,ã&Ò\r','7è!RÁBÉ˜¸~çà^',1),
('õBKÁ8I²àù[ÿW','uÖ9bË‹Duş0®æno',1),
('ŸåMCA¥€X_««Xj','‹(\ZãÍK““b\\_G#å',1),
('4Ÿòè@¦´¥wdHL','ªıëä§çFœÁ—õi²]Œ',1),
('Û“ÛÕN>²™Ó\'É•(Ç','e‚cå‰IB–0n‘¹WÃ™',1),
('áU±fBN«\'~®ÁÖ}‰','Hı]\0íbDd_[Û¨@ˆ',1),
('æì.O·­`Y-Jæ1g','±xbŞêH œahÑ3k+‡',1),
('	3Ñ,Ì„O£8ŠÕ“PX','~d“øDFŒjÕ°æ—0',1),
('<G\\«WNó¢¬ºUıæé','î•FvME»»¯âMº',1),
('ÛONkK÷¿@B­Ù@[','>ƒ°ÓˆH2 NHn†\"a',1),
('òß›™M¬…8ƒayhˆ—','0»ˆÃ#J°ƒ‹XT4—ô™',1),
('\rĞzvàB}¥ºĞ-Mr£','’<I”VC·ê¦’r?J',1),
('\rhÅ;Kù®s}oWÙ','7‚&B[”Ïbc\Zb',1),
('\r€˜¢ÑÙL…—\'ÛP3â‚','P—»bäEœ­?êôß»ú',1),
('\r¯$u5×Aí¯@b\'ŞÒ','Ã-÷ô®ïNa»D‹Å¨‘«O',1),
('\råO¶çC£‰iÉĞğE¹W','7òŞ¬xÈM9«öDCó·WÍ',1),
('i°°E,şúÇÄN\r','\'°ì¯O\0»õ§ÑàÚ\n',1),
('óâˆHŸ”Ô\n¢¥¢:+','L9A^8ÃN¡¹X‡UÀLƒ',1),
('\\¯Ù8BI¶…z~ØôL','œÖBj­Hê“‘[’´L',1),
('p(g3fD…­‚±\"8Ÿn','kŞ¿«iG-¨}¾öTå¿\r',1),
('s°+WFa¡j\"ŒïAÅ','ËÅe˜¢bFe¾Ìœ`’',1),
('úJñ·KC©Ğ›T½Ì ','Ién²€J™“Ê¿9Å\'ô8',1),
('ûƒÚÒrF–ÒÄ«@#õ','ì8ğ\'ÌOá°¹ûóÆĞ¼',1),
('acRğMÖ”4¼)ù–Uù','ØÎ3†°A—‘<t{±',1),
('N²—Ÿ5K¬§«VBÈ','œ-Ä–ñ’F—”¥#²É.',1),
('øVÀ‰jB„=ÑĞ\0ô','¬cp¾/ùKJ†MÏxøK',1),
('–ê	PBA0”¿¿yÕ¯$M','¶Ÿø²O¶.mUm‚',1),
('ü¬tkøAÚ‹ÆP¤‡>ø','Úe?ÉpûA1¬Š—B¿“fœ',1),
('øåºEV‰\"\\µù$iJ','¨¬siG\rˆÑ¡Õ¾dùë',1),
('ÑV²´Fš¢[5ÏbF¹','äoURAC£rW£°¦K<',1),
(')7váKÔ”BuN£-Å¦','K½\0=GB½–á¥‡,L',1),
('f‹?ZN[µO^B’wqµ','÷½éxJÀºsŞq|\0à*',1),
('lÙ¸ôóFSŸ¡¿ B¢','^ü”ÎAE¡q£eÚQ±P',1),
('ÕñÚ„L·½ÍÉ8{8¶','xª©ÔJ)˜eç@',1),
('š¿ÂLKèºY_^Ä¥ü',',$\Z´PLE*…Š\'¥y¹i',1),
('¦ƒADü¦e0>¶Ò','Øíô[N4H?‰#ÃKqÜ¢',1),
('vò\"”]N© >°3FEƒá','ÁGhfâ‘F> Y×.8,¡ƒ',1),
('â¾3EM+¤qÒw<Djâ','%¼³$¯ËHû¶\0/Ÿ!tV',1),
('ş@E¨ææúçtî','ÃóİC}Kç…¨—~ŞhN',1),
('›‹«MH­4:÷¹Sá','õH³ºóE¶’uˆğ˜Òù',1),
('ÓävmoOé¤„òÒ…Y›','¼¼²(H#¹=ã¾œAA',1),
('â|¨eBƒ›ãG9™wp','~Ü8èäE„—N~&¸Ác',1),
('è˜QZàEr¥Ù†ñ•¹','!àåA¿“ÜV',1),
('tå2OKš–ƒ%F','Øà”r+N¯»~y2z+',1),
('u5’¾óI!šO£	»Jç','BCµù£G±´/kÉ­YåL',1),
('u×EÿLBi“W÷‚G.C','œŠÈ<€GÀ£8hv—7è',1),
('\\ŠØ±ƒD©¯­­èKôü]','¤ş\Z€G¨¡‘³|kNT_',1),
(' Ç¦Ô.Kî¼²ñï¤¬','¢Tõ˜‚ÏI%ÀæW÷Ô‡Ş',1),
('!û€ë•NC…€a°Ü','™¡‘®/eBºUĞš5,Ü',1),
('!ë­GPßJÆ©Ûå[GêRí','şâ*«i\rFmŒ\ZW-±úõ',1),
('!ïEébC¼˜õ{ahıêÆ','€¨¸Íı”MÌŒÂçÔ',1),
('\"G¼w N–¥+:Óä_Pá','¬XıNe°®bŸ{j',1),
('\"u@G\"”¯Óí®­K','ŠÂUíÔJÿœ”k\'oÚÏ–',1),
('\"©^ªYG‡ã„ªu','dD†@d®\r/)4´‚',1),
('#_ğşÅ½C¡¶Ğrå[$',')åJ=¶E	§±”ÓÀÉnş',1),
('#d¤cÒ HªŠOoÏjùë','²pAÖ×Gè¿h–Ù}ÃI',1),
('$êOW¯!úğ[jš¾',')0D”N>“e9¦ÌÑö',1),
('$8Ôp¬LÊ½Êùßƒêûé','æÙÁ…üJÁ»½\rI[œi',1),
('$ú¬‰â*J6>_VA íJ','!6G½±v5{ºŠøÆ',1),
('%Ô!ÑO·©tZ f8dö','x¡Ëj«HÚœÆåà\"{©',1),
('%3¬zyaL˜¶b<§ÃÎTl','ÇæjsŞH=Œ˜{Œ?ÒÏ',1),
('%Lf™–.Bi‹P((¯d÷','§ËË,–€CS„iz\'ÀÀ¼',1),
('%gÈèfD‹¨\'üÂ¾„#','DÏ¾EZ@íBìûçQh',1),
('%É¥UÖNıºá>¦ ¼¹','îİ[ã‚JPÚÍöp0ô',1),
('(«6,UDí¦`2ŒŞãk',':µ?6 N›¼Ğ†Õ(@d',1),
('(ØšúÇXI³êòÄÓØ','ïd)cø+BÖ›KñàÀ¸{u',1),
(')3ƒl‡6ON¶tB(bBß','”E¸è\nM™¢ş%0G_',1),
(')«£ŒİG©”+E8¬m\np','†4ümê’D\Z¡ŠN¹İÆTm',1),
(')Àµ9~ßM³²Üµx»ÒP(','Jp9{ùG˜š}-P Gï',1),
('*J˜5ÉçG¬‹vSıÑëÀô','\"ìŸÎ:¢B’¨íB;Ñ Çø',1),
('+\Zy`óÜAÙ·¶Æä•C¤','Ô’®àG« \">¡²',1),
('+*Ü2wˆJ4!ë“P’','¨|ép*TNÑy³¯íú',1),
(',ºÌ8HMh”\\Û#—{ö','düx­·ÈNÏ¸±6F,²ê',1),
(',a\Zy±ÌC[µš³Ö: 	:','£ï”¡uÀJ?¥B¸|?/Äï',1),
(',ƒ^oDÕM¾õçš\\è','¶ä7nAt¿¬|Î½Ë',1),
('-X„p“ÜI\n¹rŒ3”$Z','@Ó¿Hˆ²J#‰LC¢úÍ(',1),
('.·­çï–LÍ¾Ëº´°±2','İ»o„ÕIIb¬¤¥$-ÜÛ',1),
('.ÀVÜ\ZJ/¤XP\0•-Ó','¹*ü¬ÑBn»ğ!ËrŸ',1),
('/W;jo_Aƒœù•§(r',';o×\Z‰XK\0¡¨à\'Dmİ',1),
('/ør`“\'EE·èÕûnİ¬!','Mÿ$ÍôGç¹÷·³n»&Z',1),
('0bF»×»NñÕFó¬ªŞ','´†ÀXoJó´IŞ-Ã',1),
('0•…øÕK´œ+>múp','üAíéTÜK„´H¾ç‹1Ö',1),
('0Ó\"=§yL-¦³ñ‘ ','½~õ<!A@ˆ@g=wó«',1),
('2Ó£\0]DlŒ¸Ï÷q','Ğ§±—ºNÖŠ‚ÁkX‘',1),
('3~CƒJUŒ6G¥Àia','²°Ä½ID³F“PM1Ò',1),
('3ÔY8ª}F¶Šfª“Rç´9','¼(•!÷1O„ÀÕ‚Iµ…',1),
('3Ø\r„ºzF”wl£~àÿ','m{¶ê¾H¹‹a\'Ÿğn‘',1),
('3Ø\r„ºzF”wl£~àÿ','‡Ì—vKf¥T×	wp¶[',2),
('4{œ©ê.Lˆ¦Á~F)âÅ','ÓO¸@±±ëÉ¨zgş',1),
('4°á®3xCÆ²Ï>i°Í–','f:ÇúªYJ[€¸šš<Ğtˆ',1),
('5l]ÉTROd¡sCG’°6','øo’á¡DŒkráğ\0ş',1),
('6ÚÖAâëL^@fi‹dá','ŒleóCOŞ ¸0u\"¥¬',1),
('8/§€gK¼7üåæœ°','ïQş>\0G›qşôğñ[',1),
('8R#«”•A˜†M¢:Ã¸e','³‘æîûßKãŠ‘”L‹jËw',1),
('9 ¦oŠDO²S½Ê/¯Ëæ','ÿ¬ÅIE\\©5cä\'„´˜',1),
('9F~º–OÓ—ùo²>mÍ','ŸXL9K@Aƒ¬é‘ñ7œ',1),
(';4è‹†ÆI¥?O]‚ûİ','uh»…ªJÒ­‰†,å±8H',1),
('<5	>ëpL¹ŠÇG½{¥','Ô’®àG« \">¡²',1),
('<¾çgËMæ©÷³µ’\"u×','L½Cæ¨Nš™,?wLò\0O',1),
('<ë2û¡N ù3ÀµŸ','BàÅG]dO|ƒ/ÇÙ ¬',1),
('=9–åMã¯¡XƒSÕ±h','—„¶@@œ°ÛÁ—j',1),
('=§\'OB•s—şGMô','T\nĞÿ¡NB€Ø£LôJ',1),
('=;ÃßD)–\\Ç»°…J','âÌnñÂJæ¡ê~Û9F€ ',1),
('=ÀùL6•êŠâãe¬>','ƒsÎ‰VîLq½ZœLµ¨',1),
('=â¶ ¾LI¡	oÔL.','ÍÇ#@lJQ²õ};<3',1),
('>Dr\"^ËF«¤zqNŸh','ô»®wşD§§å‰ÕËĞb',1),
('>ì,¤”K¸¨ÕZÒÊ¼','üò¼(KÇ“AÁ]%÷•Y',1),
('?$#êH|˜1¨?®á','¾b¥œeFD?¬éGÂ`Uá',1),
('?D¿Ù*G’F\0ª8u','%†JOÏƒ3g˜Dâ8o',1),
('?á¦“ç“LÇ´|ƒ#©V9','[äeêE[!©«ç0à',1),
('@†æw£E¡§\"qM]@4 ','lvÿ~Ió€Â¦\\T',1),
('AçpÀ”O%¿ˆ­ÿuöÁ\Z','8CÛ-{C*¨“u­…<«',1),
('B%±ô»Eè™HÓWğy','gJà|GqI%²ˆgbA',1),
('B5;ŒÓ\\H.´\ZnùmføX','(dâ\r‰@ “o&7.CÏ',1),
('BYK7\'ŞEhšOo³û«”','iòo8:¾M´’¸Ş?º',1),
('E@[ÓH‰‚\"iÛ†ƒ’','ŒAIBÙyG¿Š·p‚µĞ°',1),
('F+µwÿıL¸ºL¿O~É³','k¯‚~A\n¤¢v»k€ZŒ',1),
('FcãÚXNY¶t C­%','ù\Z½ÖızHC–ìÛò5jß:',1),
('G_ÒÂUÙBÏ¡^Íz¡YÚõ','Ì0FµÈBaš÷ğ\Z—Æ',1),
('HÈ«¹ÔKÄ¦ËÿQÿ¦ğ0','‹üR‹õì@€‹-?”?N',1),
('HÜD‡îLç¨ ¡àÍÂÒ','sŞ4êËE6¯2ÚDYp\"O',1),
('I	/ÿ¢.F­¼xö\r Ñwğ','™ºøòOÍBª„£‹Z?Ş\\',1),
('IZ—÷¨ñNÙ•Şeé )À','N>ú‹»C–•¿š¹™O',1),
('I•¦TğEªÅWóa!Ü’','ç÷(ÊÁãB¯Æ^á4r—ö',1),
('IíÅÆØDÅ¸n%c­ha','Æ{_ÂÙC\'¢9rA–æ',1),
('K\'ÁÀ„K‰=ñÓ4³','wjjÚFÔş9ãkqÕ',1),
('LJ&™¿.N_µ[ÃQæÖ°ë','$¨¡dá¡L×´cB»#•,°',1),
('L |®@ƒâú8F@gÃ','îGÁ™U”DÃ€ì@Ÿ~µ',1),
('Mkc.\rïJl–®`é;“','{Ø–R…{E-œp6Ji ',1),
('Nƒ(Cá¿5›šqÀ¸ß','€Ë	×6€J´Óù‚/i›u',1),
('NŠµ®Ò…B[¨l:ˆ+ê•','@Æ˜\ršÁE‚„¦xß\0Åùˆ',1),
('OóàmÓèHÑ‰ø¼*9ŸÉ',']ŸçqÑB™\ZºÜÎ3ÅW',1),
('PPUÉàA«xZ>½:\Z','ŠD­ëYJ¿µèöÊ$NŞQ',1),
('PÀõ-II±zÃ<y‰Í','À\rSËCĞ@_¸İ‚‰@*=',1),
('Qe¹¥A=š\"¤õ·U”','¾<ÏéÆL=¢¹8¬—©É',1),
('Rçš\Z¶dHiŒ\0öÂPã','cÛ‘ŸwF{©D1 ï€',1),
('S/JÔcˆEV–¦É­{','ºa•!O_˜:–Õ§ŸÙ',1),
('SSÊ—8>Iµó\"û5¤›','Ë×?šùH\nŠ—ü{­’e',1),
('TygG¼I°6€ÓÑX','Çw+Óü\"L-£Eü\0]',1),
('TÚDÇ6|JcºÕKë¥‘àg','ˆÃjb¢*L“‘ ¤W*Í1¼',1),
('Vj­îHV¾Ó9iiù','#¥AíuND9ˆ„K=_dÈ',1),
('VšÃùĞÃ@¥«ÔÎ:ã','V$–pHù„‰|Î\\[',1),
('XQ² MAzª·£Wş„','Èû—»_õCÖ¾£$>™×ä',1),
('XfîÍåH™¶8Ó@¹À”','Æ°Æ$òt@j®wÑ¬2¸¸',1),
('X¹/›ìFK3¥Ï0›ÔZK','~?%ñ\rC“©îl]1ï®',1),
('Xş°æ=\nOÛ’ï/ĞbiÒ\'','>Œã­ŠFù¢>BÀC×õW',1),
('YÛØ]6¨M½¹¥ÕóPö`','6i\n0ˆhCoº¥8Š9ò•',1),
('[÷	£¾G)òÂ#*œ ','\'«áb¡™Jò§&Í@ßp¤',1),
('\\yÓO)@4ªüG‚Ğ‡‡«','®\\‚N8»¬Vh=%¸',1),
('\\i Á ¹@\Zš? ¼\r¯†','OªÙyëÄH–Ü‚Ñr|Ë‹',1),
('\\¶ñ5ÉA®²³DkÚÅ©¬','2*ü©Nbš™|¼-“à',1),
(']d-ñ<9KÄ«µHºú«à','ş¶³pµ%Bí”“¦Û1ˆe',1),
('^àƒ,sNø¹ÖóHQÏ','kt¦|·âJ8–³\nÕ´ûµÁ',1),
('__Ş“JK°½Oíå+','ªÚY¨¹OÁ¶TÆ_fäg',1),
('_¿µJçHG´a”=ÓLù)','¬ô§3ôOÆ†\"=²ìîòc',1),
('_îuêqéFÚ†JÆS)\"‹Û','EAÒĞL?ò_â$3Hk',1),
('`	¤€êîB²†hsóÁÈ','ù´|%DÈ·æbô¤?!·',1),
('`¡p_8Gÿ«Ç~kÃZ/','Ó aËQ$KWª-²40x',1),
('`FÉÓãÑN¿şøR^Ó','K^£ä¡K2©_¨¶\\\"€¤',1),
('`½{]çGG®ê8^«€(”','¾bM:ßzAÅ–ZT÷2‹ö',1),
('`İ_AõB‘ÙëbŒgO±','éGªñ`kA¨Ğ¾[»™',1),
('`ú+µãÖH‹ÜXø{ş‰','<pó xÉIŸFÀ>MTóõ',1),
('a5ôï©ÎAS‹p™>É6$','Ûò™rCÈ˜#”İ`ùå',1),
('a˜›4ÿN¦«ıˆ¶ÿM','RF.ÎÆ·Hç³ê`ÃSà',1),
('aÀ6\\3AX«-Ü;¦êx','¬3’Pz)GI’µÄ8À©',1),
('b‰£= ÁAø‡ôŞ=6¸_','9¡óâàO’ŞÒl¢L5F',1),
('c?\0¾K HÕ4ˆêÆ','¿årO¬qGğ²2”“y&Dí',1),
('c?\0¾K HÕ4ˆêÆ','Y6ØzzC¡›öú> 0:',2),
('cW°¡ùÂNç©‡ù_-é','\nÖb£›C÷¦DJ•À—1',1),
('c‘FtVJ‰Ã@s‰','“¸¶SnDô¿ûw†¨zÁ',1),
('cŸBğsEâ‚x®Ó»æ2û','IT°ïy\'B-¬ã&\rÄùò',1),
('d\\\ZG@í’	‘š\"€~','=SÄÑuA­[d– ˆ',1),
('e¶\\^¹GÒµ`t}k4™','=,i!\ZF ö¶’ï:IÇ',1),
('fRšÎoJÜ©\n?lÑA','NÃQ?6F\n¯Õ­¹nî÷€',1),
('fSip¸I%»db','<òÁà#B³’Ğ$·ã',1),
('gj#Ó\'vMƒ™j}sİ£0','d.muB‹6º@%½\\G',1),
('gxí½C{I §±ˆw ','&ÅDÛHÓŠ\'\næÓ+c',1),
('g}.À«Dï„ĞÛ\"ã¢%','\'Y¼²¨K>5ğ÷®ĞH5',1),
('hƒV JÄºÊ§›×','æ“Ò\"HIª—F¹p+¨vŞ',1),
('i¸|oî@é‡væ+ª5','ĞÄ#5L^NW‰v§*V¯¡',1),
('k`¶¢ÕD‚.úùƒ@U','8|s¹8@Ù‚Dú‡vÙt',1),
('k™Ş%r÷G+õ@¡©x','\\qX;F…ˆ5a”`Äf',1),
('l:°ªt¬NİªO3òX”‚Y','»\rPÛAT´³<õµñ',1),
('n,ñzëíB#«`ı=¤','SÎèI9+H5‘yÖJ@^©ƒ',1),
('oˆ=¨{êJ¿‘9tÈok™à','Rò$Šl×Cµ¹§EgµÌ',1),
('pKº÷ŞDø­Ø¡ÚŞI','!U52Û}G”¯½°Êvæ',1),
('q\\¹oK­h\rí»Øz','Ü‘®Ñ‹<D£¤Àõ>x8Õ',1),
('qV«(øN—ŸK[c–*~','Dî,şOì«S­á3½İf',1),
('rÙ€F¬ øÖˆ¾ã','p\r¢a¢€Iy”˜4-Şz6',1),
('rêŞhB¢¯ÁÃ¡ L','®å7KàNèK¥AØã',1),
('s@t¶\"F—³àv¹—G','‰\nŠCÔÇX€V®Î',1),
('t çO;kMH¯œê<ìæ†','\nğŠM²\'@ô’Bw49#',1),
('t@ıDÈäIª€Ø|Ë','´ÅùY½L(¸¸•N›ïs,',1),
('uI‰×,D?‹†İL/¼H','­‹ÇÒvMü©`,Ó™ıÂ',1),
('uËèÊåNò¹-([È\0›o','\0æÓÙ¨B#J=Ê;J',1),
('v5½\rGÜ„öâeø','ó”\n{º€BX´\Z[h+dú',1),
('vG·TVMD…?ÓóåÇ”','“»óÀB1\\ÕüdbÙ',1),
('v’×Ù,@»ºre1‰^O','JüıAL9­æ\Zq8¿èÔ',1),
('y…à°Ds¬°Æ¸»×À','>§K\"tNu‹‘5bUûò@',1),
('{¢$ö0ÄIè‡ú!Ğ\"È\'','ŠÏ,ş 8O”¾ÌCç÷¢¬=',1),
('}t8ÆÿğLª0pQXvgD','9ÉphàND­0ëbÉÖĞ',1),
('}£ÀÅ,B7šl\\Ì‚ÅX-','„ì»õxMr¿ïvøñBy',1),
('©5ŸÇKˆ·¸ÌÉõ€ş„','\0\ZäÅyD\\´~Lc°‚',1),
('€7ÇáòvJÕ„g½šD','ª¤†‡)Dd™œ$Ÿ—øè@',1),
('€—¢`cM\0»©½8Km','§ÊÚ×iE|¿LaŸè¢Ö',1),
('ŞC»®c)Ä]ƒÓ','\\°¢P‰E\Z€¡-G4Á<u',1),
('¥ø‘ëÏOúl¢ÎÂ‚','íÚ7gGH¾‡\nÛ¾C',1),
('„ï%±á’F¹}‰3¶z#','µoJD#\'‡OÍ™5ÿ',1),
('…B¢™\\	A2´Å¦‰×>ı','…HÇMõ¯QYŒwò‚',1),
('…\rKÀ¼‰Çütãl','BCd*+M£´-ŞŠË\r©',1),
('…å¼jiãOÁ„ı,¦Ñã','Å+¬|<cA-ˆAâ§Ìä9',1),
('†!k‘«H×ˆã!DÂEo',']­à×­I0¯í§üA&',1),
('†à?4IÌ˜ =®*pt','™éÓĞ¡LEv½/­8ê–f®',1),
('‡r±Ÿ.oDïƒ§ªXpşÎx','p;3Â³3Ix¿uÂòSï',1),
('‡â™9kåIÀ»5õC…ÂÖ™','pFü;AÊŠ\'·,ÃÍp',1),
('ˆ%[u»G ºØ¢V',' •,õŸiM#—R8S[„~',1),
('ˆ­ä˜Y‡K!¦s`î=6¡o','yV#ÂòFF¸²@r_ÊÑ€X',1),
('‰1rà\n(Mş­6ëíï¶óÃ','‹€WÏKG¬™MP1ïËÀ×',1),
('‰ÜâN¶Mêªéèêİô9T','^o‰RM÷•z`_¹‡',1),
('‹!r°/NqŠ3Ä:æN','™ÜrÑ)L¶¤QÛé\'ì',1),
('‹IÎÈ¯JÅ“æ¿ÇR2eË','ÚÊH`y›Lä¹z',1),
('ÌÛä—N„‡!bw^','ª.òÔA‡uÉFèXñ',1),
('à¯IÇúIËÍªºµS…Á','4ß}(GÏ—æuæt#ô¼',1),
('õ÷¤t½O£ø7nã”ê','tÀpd4NÙ—»íë8ƒşõ',1),
('È¢» Fšf·r\'“\".','ck\'Á‚KE!”ÂT tO',1),
('jJÆO,¨%­^(¡®','¸XÕ2ïA,ŠaíŠÌõHÈ',1),
('dáíO%Oç•º%èŸY—Ê','y~q•ÜúN³•\'4R—Ä',1),
('§Ø\Z–Ä@)šÂ Có¦Nì','$´§CÅC˜’<ízË#',1),
('åÊíÁNµ‚õQ``K*s','úOŸ\\/\\L™»k>«F¦',1),
('SJí•%Aş›z&È…Îß¼','|ŠÿÒÎKŒ¸Í+D-1+',1),
('”6\'fF’•5ˆÖÓÕr©','³\\rtóDF±[]Z/S€Ğ',1),
('•<­ŸhOËºnª		`','ÃÄ!KWÇJEœxÆDçÙ0?',1),
('–`zP»Eò¯·n§QÌù`','pëÍYÊ`O’Åi8ö',1),
('–À¡î¡•EIóÁÑ Mvh','ÃÍÏµs]Lœ¹zô(¥´ÿ~',1),
('–ÊxO¥Gêfp6³','èêcİÑ6C²±C¶Åõ',1),
('–â4ÄÿH±’®¹pİk#x','vÇt_ébJ„·çŠ%Êå',1),
('—»RéE­–ãŒ€°','ó—äM\'¿òa-nªÚ',1),
('—l«²/G¢³ÙE5nA','ÇwÁiÈ@VºÈŞ¡\\¨',1),
('—l«²/G¢³ÙE5nA','q~ªk¹;CŸ«ty¥¡Ş',2),
('—l«²/G¢³ÙE5nA','ÁTÃXòA¿·{‚=\Zï„',3),
('—l«²/G¢³ÙE5nA','ÆÉ	zš+M=Œ-&Í‚«\0',4),
('—l«²/G¢³ÙE5nA','ú›5¯\0O4…rÔĞ¤KÊ',5),
('—l«²/G¢³ÙE5nA','¬êæ`ºL)Í$ĞÃË',6),
('—l«²/G¢³ÙE5nA','äa>äBë¦Ú\Z€v$áz',7),
('—l«²/G¢³ÙE5nA',';Ğ¿R|FLè“–Ûü_§',8),
('—l«²/G¢³ÙE5nA','ÿ:[›Dx‹Ä(po ¹',9),
('—l«²/G¢³ÙE5nA','\'e±»/¬C‚çë%Ëkäª',10),
('—l«²/G¢³ÙE5nA','±ó¡3 DØ‡j}ÊT7',11),
('—l«²/G¢³ÙE5nA','W+w`ÅúAÊ¸Y±/›8',12),
('—l«²/G¢³ÙE5nA','YN$ÇÇÁBi†_\nœ™¼î¦',13),
('—l«²/G¢³ÙE5nA','m‹Fš3Jk”ä´)ŠÿW',14),
('™`æ÷2I‘¾;)Ár¤','³ü§ŸrºHíº~2_@“',1),
('™…Ğï=&A‚E©sôŒT','1ÎZc«?CÓ¶Ï{âY¢Ï',1),
('š+[dí¤@†­Ll°3d9','ïvª\"Ú€@ñ¼˜jtÖqi}',1),
('šrçïì¬B.œ˜³{¹–(','àòAÉæBĞÛº¶ß†a',1),
('šÍÌú\"¡LL‡ş9l\r','ï_İ^ê~Oƒ”E(v¥÷',1),
('›K!ì!4A¿¥`Ü‘hç','w‰¯„*HÜŠˆ€íä°',1),
('œ6g¾ÃB£^aÈ¯§c','°]„W%ÂEQ“¦cùwô«Æ',1),
('œ…H«I|K4)³‰e0¤','ê\0GDMğµeãQ¥:-“',1),
('œ–†ÏsBÌ¹d[ónµñ‚','qˆÒk6LS¥Oÿ°ˆkZy',1),
('pg×ò7GÏšOÅÈV­ç','Ïsù¿_…A)ƒ¶¯²FRR',1),
('ä÷q57Fw úËV®\'6','Óff±I‹òÌĞëô ”',1),
('¡¤w“:|OX†ØZe(÷','è&]«®@IƒC„»¯}k>',1),
('¢Åx­BOœ¸H€˜aÿd','òzXÑ©æBbºë$¡ô’®',1),
('¢ü³³©/D×ˆÿïKt‘À',']á—ò^@a–š­4	\0İc',1),
('£?ôøØåKê¸\n=h~]Å','UaòdA]¥Âàúlş	',1),
('£ÔmM¸If‚ÇÆ†´Ë$>','Âœ¥AŞ†ûk ~Û',1),
('£ÔmM¸If‚ÇÆ†´Ë$>','6ûá„´O@˜tTzı',2),
('£ÔmM¸If‚ÇÆ†´Ë$>','“(o@•X×‘–á>›',3),
('¦[¸\nOßÉCFñ=ï','É³jb4³M:‡İØõ$',1),
('¦ÔJêVFf…`\nÑœã†©','ˆîÃWrDÌ¼:ıœrÖÃ',1),
('§I.éå’G;¤›ĞËY@','w±¢\0\0şB¢‹ÓŞ\'%Yş½',1),
('§ŞÅ‹…Nòƒõ[8I9ò','üBˆOƒM$¡_R\"pÛi',1),
('¨(3­w°FÛ¡³qÜiÒ²˜','æ~ŒšZÜAo±ãî“\'|˜',1),
('¨q¤-p\"J¾»²Û²Öû','1¤¥5ÚEë—FK¾s·’',1),
('¨a•0ÀJ|•œø^9p…ë','bzxLC{F®ƒ,K&ßrği',1),
('¨°R ¨•IŒšØa:­&K','CM’NèwI=•G¥K–¸#',1),
('©Ÿ3[úm@t®Rc^x\0ÁE','‹gU÷ùJt—ãh¼$',1),
('ªÀ³5iıK^°â¬7Amã','ÑÓš¾$N•m|™Ã>¤',1),
('¬$ĞÒªOc£\rÍ,#Ãíı','Î	SHt¨¿GÖ²ç',1),
('¬oa’ú®HI¨ş˜ƒ$ıâÏ','=¼%QuEâá.yÎU',1),
('¬Ñ´\'•KÀ…¶@öC}ˆ','òï­A~ÕK±…‚­ürÄ',1),
('­»hOš‹Gz¯½ßºÎ\0ê','Á°¸J}BC•ò(¨ò\"',1),
('®*ÿ.‡ØNúš¯Õg^Ë%~','İ/=®@jºñ,õŞÑ',1),
('¯,»LyLxší:Ğ©oò{','®Û]H\r Jı½µ›\'X;ÙÏ',1),
('°ŒöåN	‰FÃÚ?t3','|	^\nS8@K•Ã§ ¬|*',1),
('±–}[8K8Ÿf3ìÅ','¢)²VBKı¨ÃnñaL2',1),
('±ÛLTM¨¶ïŒô ¼(','zéÍDä«ÌPVıÆ¢',1),
('±ªÈå2rJW”¹@Ó?cÍ','³	wr×K@ô\Zü?AD',1),
('²^Å©ñIb’Õc8,»','ÔƒÉ(jLk¨Ï/W†r|=',1),
('³YpóL?˜™7•x`B ','Œ†‘×\Z9Bõ€$QvĞ½®)',1),
('³§oı‚+N/‰*ìŸºŸÇ','´qä2æAu¿’Ù“	¬•',1),
('³ş´VppH‹ƒ‰å/´tJ','‘ÙB\Z¶‰@»¡¬%NpÛø',1),
('´84$‹¬Eí”äÜ‘a_#','£3\rËE‡‡[gÍ§3w',1),
('´\\ö7ŸNg–¤ÔÙààÒ','=Ã EïN¬•\rJ½ ß',1),
('µçÄ!á#MR²z@¿ÅL','yhj\'d°MAˆÃF†.¾;',1),
('¶Øb+qKcŒêF¿Cªë','½=T÷¥Jû°â_µµ\0B',1),
('·ìá¼\ZC–ƒ¯u\\$~','ïjq)ëƒD£–ÜàğDœ',1),
('¸{T¨@H÷“Şé’Œm7^','}ŠKÙhGD*°Ä`şÒJƒ',1),
('¹¶jºFS©^ÅJ\"?8)','*÷âäãB]©Ç·8¯ÛÛã',1),
('¹é†<œpK‹¿ÚÉá·	','ìwÔ)`øHy¢UÛˆÔDªK',1),
('¹õıB1C(›Sc¶+{','5P–ŸÏMÂ©p°2tûtÚ',1),
('º–Ø?KN³Èø:õÁ','Zå´ÕÜM„–öåh±Í÷+',1),
('»*J?ƒhEí‹a%nŞ‹íd','xùœ¼^Cª	÷‹ŸV',1),
('»ug@Hv¹ÿ[aÃ&Ì',']:‹#ABO¹vÉ…s=Fo',1),
('»â¸á·±B7·ê9:¸:¤K','—Â›×2vIc‹†%FNéÎ',1),
('¼D¾~ôA@£|Í®Êì4ò','Õ„7_y@b²úÇ…î\Ztõ',1),
('¼•MÆ†:N,£oWğ`àæÇ',':µ?6 N›¼Ğ†Õ(@d',1),
('¼•MÆ†:N,£oWğ`àæÇ',']7„®,jBsƒfÃÿx',2),
('¼•MÆ†:N,£oWğ`àæÇ','Å+¬|<cA-ˆAâ§Ìä9',3),
('¼•MÆ†:N,£oWğ`àæÇ','‹kœíyjMp¡ò‘¥J8PQ',4),
('¼•MÆ†:N,£oWğ`àæÇ','9’ï1âkC¢ŸI.#´',5),
('¼•MÆ†:N,£oWğ`àæÇ','ØÎ3†°A—‘<t{±',6),
('¼•MÆ†:N,£oWğ`àæÇ','ş¶³pµ%Bí”“¦Û1ˆe',7),
('¾ÎÂ˜÷hF>§‹Á•.ZØ<','Ë£NôACƒVRù*',1),
('¾óŠ?rÓK®à½RV)1©','‹ÿ5s\'Fµ’ÕJşC u',1),
('¿Ñ>¹IÏ¥°”Æ5','¦ú+“:I6¯X%,kv#',1),
('¿àÉnƒ8B\n‹-Ÿ¡¥v2.','=µB5ànNL¥~HÜ‘‹•‡',1),
('¿ê¾ü%^D‡£™·[ø_eÑ','1s*r#ÁN¨¶—{ü­q ',1),
('Áhm\0VXDš¼TåÏñJ','Ôaj„7MWœûì„D.…s',1),
('ÃÑİÓ5@ß©|¥ÍCnı','Ğ,¥ƒ§L œúª»/=ªa',1),
('ÄtË²-JG–×¿•&Ò4','#ŞIJ<ÊA\nˆ‚@Áìa7',1),
('Ä‘e·æH0ŸñxegÈwÁ','/ÚŒG¤ŸNÿ¹jwÜØ‰',1),
('Å7ÑPpA&»OHOˆ­','‰M‚UØáG*ºÒ¼o7Œ‰',1),
('Åe[g,9GœºuÒ¿M·q','Ù´^ÛS*AÎ‚\0Wœ¥­ì!',1),
('Åë8:_DI YGş“f','¤ƒÆdM\Z‡l|gñö',1),
('Æ°«Í´ÑOD©úñ3Kdöd','îkF™?)N/Šk‹_\"™Ñ',1),
('Çñ&8ş˜BÌæÍ·>#œ','ºdÈjZ,FúŒ3$ÙWÈ',1),
('ÈÖ³‚ô)Eõ¿fy[¦€','ØÁF@­wN¯t\"Ú\'ô',1),
('Ë}™ŞÕCÇ™Ï\ZM','ßQª¾ƒ@æ„\ZœGèáù',1),
('Ë}ÃMšŸH˜©h\'ÁÔ<.À','’i‹“@‚™GÉÌAd<',1),
('ÌèÉV–D\rŠ5R›\'ë','ß§}gì*M1›úÇ=uNq—',1),
('Ì”ÃÏ<¹JŸ®zHr%0Š','ØGÚöMO–3,Õª~!\Z',1),
('ÌÛdgÏI@\Z xxåmZğ','­NMÅ¹{ÍËî©÷',1),
('Í.ÃŒÎÚJ?ƒYúØÌ²…î','éH‹¬|vC¶€sXÂõªÀ',1),
('Í­2»ºN §Rš\ZyÃ”','pYA\n—òB-šˆôôÆzß,',1),
('Íš‘ÜçSD\'Æ:sûÃzÖ','SºòM+¼ED4Ğ',1),
('Î¨o¨O[°ß^9û[»§','t.×\\¶ŠMk¯óÚ·l/¥Y',1),
('ÎfÔp?@Ñ¸à‰YG¡','#ÜwxÖJ´®©h\0ş¨)',1),
('Ğ‚\"FõM,ŸÎİæ…¿*p','0ğbPÑıF›†27IBÒ­',1),
('ĞÀ¯4À¶AÉ‹?ÀÑ©\r','ëÀŠs9ACÍ‡Í7ZÆ0*ø',1),
('ĞŞì?ŸrKÃ®×D„øÜØL','´èÙp	YGçµò¨I',1),
('ÑªÜ9¡Ml‚,Jeå/Ã','Ú\"…„è®Ií\\P¥şÄÔ',1),
('Ñã)(\ZJº˜îÓ6¯\0','×2ŒæûåH,F9\"Š\"Ú,',1),
('ÒócÎ®äI­²³#y˜h\nï','-B?h’J‡µ³Ä|:',1),
('Ó,rWîOrº¿Äëß£š','céËA¯cê¾~ÀÙ',1),
('ÓJûvEaƒõ‚ÅI\'','J®ÀëòBà‡ìqƒx=É*',1),
('ÓÏ¼iá“Mµ³[	²„Şk','Å&š¬båA ‰.rÄ‰9;',1),
('ÓàíäcêNB†\"!ÖŠÿ','p\Z¿=›M¦•g:ã¢®_\r',1),
('ÕGé?G¹š•\nßdîY5','X,ô¦ŠDK•%m 0Jë',1),
('Õ¥ *iéL\Z¬Ñœ1õV','å/öŠC&¿GÊRÆñ',1),
('Öµ%¿.K¡”6ß®L','Ìƒn%ï–H±²†|A4—',1),
('×`Ä¢J“°’\"Vş@_','¬À!Îù¤Dº/Ìubô6',1),
('×Ì~½°ÈIä£Á¨P(\"£Ö','xùœ¼^Cª	÷‹ŸV',1),
('×öph˜K‘a­ÆI¿å©','Òd|”w¿B—¶8u&„(Ÿ',1),
('×öph˜K‘a­ÆI¿å©','í…ÙgdI‡¡Ji¡“£Ú',2),
('Ø¨wõL+B0ƒyÒ¯=¦,','4¯m~?êLü¦\nø¼¹ ±}',1),
('Ù·d 2O®é¾¢ÄŞğ','\\¨I}µxOyµú°»X ú',1),
('ÙÒ5£sIí²0™ù¿Ro','ü\r+$GL¯Î+kêÔ9',1),
('ÚEÚ¤v†B§ŒgÍÄ\0ğt(','úbdï€{@É¿KÉ&',1),
('ÛSG”¿Ç£Iƒ…Ù','äf$ëûE-™Ó_v›mxx',1),
('Ûg›Î”çD#¬÷öÙ[\'v','ç_´òZI§H&¶§.‰G',1),
('Û›Kì;•BÆ€ù#Ãò”','ú©¾OĞ‰KÅ®ƒøO@’e',1),
('Û›M¡XM¯ÀÃBê','%JşND]®aÉd¸å]',1),
('ÛğÊ­¹eF™œLI<«£€é','/Ô®R£Hï”tùÀ¶)',1),
('İ#ĞJiHv©×Ï\"~v','pï20N«±‡%“&î·',1),
('İ(j£IIˆ¯¤÷º>øs',' \rAÚQİ@¥±d3öC’Gš',1),
('İyZ¯ußBŞŒ2\r%êå','\'QQ¥å)C+èòÉ—mö',1),
('Ş<z—QaAğ¤!)ä0í=1','à”\rY@šIAÀ0',1),
('ß+ígÌuK£¹Ìê3ØlÙ','Š9è`±ìG»ÅÉã¼‹Œ',1),
('ßX•‡0™NøŸ” Y©5','‹kœíyjMp¡ò‘¥J8PQ',1),
('ßAĞ@õ§œ—v÷!‚','&Ø³ªJ; `Õæ.',1),
('à¹g›L)M³Óºåşß','3-ñxÃC:ºõ¶Á,0Ë',1),
('àîÕÿCBES¯OvGü0Ò',' \"#æ²\nDµ‹Ò0c+·‡',1),
('â¯¾ĞFYˆÍää§’\r','ëÛË4ÙF¡,şÛƒ!=',1),
('âSæ5†ûD`Œ2äå³(T',']7„®,jBsƒfÃÿx',1),
('â®;«¼DÛ¿c%ÍôÖ…','«“¼k×ÂJ{¬±QL“İ',1),
('ä­i[½O¢ söÄgDãZ','üœèzKOk‚ùÆEpK*C',1),
('åN†jXSKÖœhéó¸÷','m™m¡ŸGu¶,×²®üĞ',1),
('åÁ\0n[F–t‹d‡U.','İñ\0âLæ¢´p_siƒO',1),
('åÁ¡µÏfI¥_u³ Ü6','‚	á6‰,Måš„ş¦\'ï«',1),
('çqtümH®†3½oÔöá','Q~öèMA¤.Ú½*ÑµÃ',1),
('è\\¶èTÁC>†›FT„ãQ','¦»-9©ÏN†ºıÂºé­Î',1),
('èƒMÆHÓÚ¼\\¡^Ô','ÚòîR@M™|Nş\n£',1),
('éˆÎ…öIAe¢•lKŞ','‰E÷Hf¹÷–(Uîi,',1),
('éÇnŸä¡H°¯ãÀ?Ã‰äs','íè~WcîJ©·Rò†úUğ',1),
('êÍAÒ\'NF˜„Œ%­ÔŒK','ğ1·’â+MÑ¤è_@mg»',1),
('ë²¥‹N¸©³2¬—‹?i','ÏŠ2) ¾IÙº”U²-ü~_',1),
('ìOË¶ãF}´>Äƒoï@','ƒä›ÑÍŸFl¸2­+ã³÷',1),
('ìsX‰¿\0N™¤N=¡ì\\ôa','V÷loÌM›™ïÿQzU',1),
('íaˆÜôÕK÷œ‡vìÜÏ5ì','(â+[);FU¼²Pbf‘b',1),
('îB_j€ME£ŠˆÖ*ƒS','ed£OS´Å‘èê.}',1),
('î¬.Ù†Ø@i¶eÃ„ıE','eƒ¯ê Fj¯@èl›?',1),
('îç¬4xD“c3ÄDl™','¨#’nĞ”FŒ‰\0PÀ}„ ',1),
('ïŠ¥åÂÕKƒŸß÷\n°s6','ÿgĞÒÉ¡J\\¡=¼z—H¡E',1),
('ñ|äÌ•ÀM–IMÂí¿Î£','şÁIf|›EK€oOF>W]',1),
('òtÄRæ›Hç½9ÓóMrA','ä´ Ë3ÕBo>ãDŸ²Ø',1),
('ó<n¾iLs–ÎlîUµ)C','ªìg\\ŞO\rº³mâ$ˆ',1),
('óÓŒÙ<ÄB»½›ü6¤´\'U','‘¦ª0U­Eğ¾vËT—ÉJú',1),
('ô\ZãèA@†CSìéÁâÈ','¢ŠGù	E3·ta3Í×',1),
('ô\ZãèA@†CSìéÁâÈ','68ş;‚:Mñ„¸É¼{[Ë',2),
('ô\\†\0Os‡%\0$\r\0','ù‚	X«Mõ™Ç\nÁ¾Ò',1),
('õ\"%h¡E¹¬ä@ZØyÈ‰','Õ×3ÀõG–›?4ÆaLÈ',1),
('õb]gF^¦ºám\\~İÒ','²»op[JÏ«Œ¢€wñÂ',1),
('õ´„£n—GÊŸíÂ+/ú¡','¯Q_çF$¹®@Kè8',1),
('öáÙ?P;@À¶|A4ßv“','H)S\rA5L0œÇéşóL–®',1),
('øİ¾­uMİƒÙ‡Mè<mú','óD+6$rG@‰ßyÏà|',1),
('ù(UÖ(úI8ŠÂ®ÒÀ÷÷','”j¤‹qJ?ƒ\nÿÛzz',1),
('ù×è1ÈHVZîrX!','?Ûô”Mv ê:1TD}',1),
('ú*ÒœØNG‡-ä»µÖ*=','ÂÕ=MˆJëš,7ø²À?',1),
('ú/Š\ZŒ5Fƒ›A`û}û','€‘p£´E\'½ô˜ü±˜Ùü',1),
('úO\Z”ÔJ¹¡3\'În®Y','lq_\'äyNÔ©Ó÷_Ùİ€',1),
('ú…\'•wE*›G{2Vl','ñ\'•Y¡F®¡9’ë\0Ôòg',1),
('úßíB›pH³İ¤‰ƒÊ)','ôª9.‚‰I§ƒ—xøs‚‘',1),
('û1V¾]GÉGÅ/\n.UÕ','9’ï1âkC¢ŸI.#´',1),
('üÑç¼M\nŠd¤ø×X','÷#|{’~Bİ±tÊICSø',1),
('ü^cw’A¨¬2fË','„ª	Â‰cF)iK¶Ë-',1),
('ıkœNÃQLÍŠE^wÇ','E-_lëMg§äîO;~',1),
('ıvàÃçO¾„¨­÷û÷\Zò','M×`¾2+Kh†£/¤¤xÿ§',1),
('ı‹úŒI@EA­^ê›§tX','#x’¤Mi›Ö”á¢Ìs',1),
('ş/Éƒ„Fz¦\"šú(»','ú/˜–ôõ@¸>®O\r–ëè',1),
('şn D#G®0è	Åv','C˜ià¸C¹¼€L8eñ',1),
('şÉ{µ”îH\rÿYt±¶—Ÿ','‚ùj¸•MS’ÕòüÏÁÎ',1),
('şÏ¾²h@C¬à áÍÑÌ','x %\Z\nA¼…*yšù',1),
('ÿâõàÚÀDn¯Şcw5Êb¡','ı&,-FÇÏIJŸv',1);
/*!40000 ALTER TABLE `content_in_physical_collection` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-09-01 21:22:41
