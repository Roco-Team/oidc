<?php if (isset($_ENV['CN_ICP_NO']) || isset($_ENV['CN_MPS_NO'])) { ?>
<footer>
    <center>
        <?php if (isset($_ENV["CN_ICP_NO"])) { ?>
            <a href="https://beian.miit.gov.cn/" target="_blank"><?php echo $_ENV["CN_ICP_NO"];?></a>
            <br>
        <?php } ?>
        <?php if (isset($_ENV["CN_MPS_NO"])) { 
            $mps1 = explode("备", $_ENV["CN_MPS_NO"]);
            $mps2 = explode("号", $mps1[1]);
            $mps = $mps2[0];
        ?>
            <img src="<?php echo $_ENV['APP_URL']?>/assets/beianmps.png" width="14px" align="center"/>&nbsp;<a href="https://beian.mps.gov.cn/#/query/webSearch?code=<?php echo $mps;?>" target="_blank"><?php echo $_ENV["CN_MPS_NO"]?></a>
            <br>
        <?php } ?>
    </center>
    <br>
</footer>
<?php } ?>
</body>
</html>