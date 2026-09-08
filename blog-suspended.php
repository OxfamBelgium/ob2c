<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html lang="nl">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Wij zijn gesloten &#124; Webshop Oxfam-Wereldwinkels</title>
		
		<link rel="stylesheet" type="text/css" href="https://www.lecouperet.net/hcb/styles/style-normal.css">
		<style>
			.padding-fix {
				padding: 2.5em 5em;
				height: 15%;
			}
			
			@media (max-width: 480px) {
				.padding-fix {
					padding: 2em;
					height: 20%;
				}
			}
			
			.tikken-404 {
				margin: 0 auto;
				text-align: center;
				line-height: 140%;
			}
			
			.logo-wrapper {
				width: 100%;
				height: 15%;
				max-height: 60px;
				position: absolute;
				bottom: 5%;
				text-align: center;
			}
			
			.background-image {
				background-size: contain;
				background-position: center bottom;
				background-repeat: no-repeat;
			}
				
			.error {
				height: 40%;
				background-image: url('https://www.lecouperet.net/hcb/images/403.png');
			}
			
			.logo {
				height: 100%;
				width: 100%;
				background-image: url('https://www.oxfamfairtrade.be/wp-content/themes/oft/images/logo/oxfam-wereldwinkels.svg');
			}
		</style>
		
		<script type="text/javascript" src="https://www.lecouperet.net/hcb/scripts/typewriter.js"></script>
	</head>

	<body onLoad="setTimeout('delayedRedirect()', 20000)">
		<div class="main" style="height: 100%;">
			<div class="wrapper" style="max-width: 900px; height: 100%;">
				<div class="background-image error">
					<span class="helper"></span>
				</div>
				<div style="height: 60%; box-sizing: border-box;" class="padding-fix">
					<div id="typewriter" class="tikken-404">
						Deze lokale webshop is <span id="rood">definitief gesloten</span>.
						We sturen je door naar de portaalpagina, waar je de webshops vindt
						die <span id="rood">wel nog actief</span> zijn en leveren naar jouw postcode.
					</div>
				</div>
			</div>
		</div>
		
		<div class="logo-wrapper">
			<a href="https://shop.oxfamwereldwinkels.be" title="Bezoek onze webshops">
				<div class="background-image logo"></div>
			</a>
		</div>
		
		<script type="text/javascript">
			new TypingText(document.getElementById("typewriter"), 55);
			TypingText.runAll();
		</script>
		
		<script type="text/javascript">
			function delayedRedirect(){
				location.replace("https://shop.oxfamwereldwinkels.be");
			}
		</script>
	</body>
</html>