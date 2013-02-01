{include file='header'}

<header class="boxHeadline boxSubHeadline">
	<hgroup>
		<h1>{lang}wcf.global.license{/lang}</h1>
		<h2>{lang}wcf.global.license.description{/lang}</h2>
	</hgroup>
</header>

{if $missingAcception|isset}
	<p class="error">{lang}wcf.global.license.missingAcception{/lang}</p>
{/if}

<form method="post" action="install.php">
	<div class="container containerPadding marginTop">
		<textarea rows="20" cols="40" readonly="readonly" autofocus="autofocus" id="license">{$license}</textarea>
		<p><label><input type="checkbox" name="accepted" value="1" /> {lang}wcf.global.license.accept.description{/lang}</label></p>
	</div>
	
	<div class="formSubmit">
		<input type="submit" value="{lang}wcf.global.button.next{/lang}" accesskey="s" />
		<input type="hidden" name="send" value="1" />
		<input type="hidden" name="step" value="{@$nextStep}" />
		<input type="hidden" name="tmpFilePrefix" value="{@$tmpFilePrefix}" />
		<input type="hidden" name="languageCode" value="{@$languageCode}" />
		<input type="hidden" name="dev" value="{@$developerMode}" />
	</div>
</form>

{include file='footer'}
