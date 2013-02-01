{include file='header' pageTitle='wcf.acp.application.management'}

{hascontent}
	<div class="contentNavigation">
		<nav>
			<ul>
				{content}
					{event name='contentNavigationButtonsTop'}
				{/content}
			</ul>
		</nav>
	</div>
{/hascontent}

{if $applicationList|count}
	<header class="boxHeadline">
		<hgroup>
			<h1>{lang}wcf.acp.application.list{/lang} <span class="badge">{#$applicationList|count}</span></h1>
		</hgroup>
	</header>
	
	<div class="tabularBox marginTop">
		<table class="table">
			<thead>
				<tr>
					<th class="columnID columnPackageID" colspan="2">{lang}wcf.global.objectID{/lang}</th>
					<th class="columnText columnPackageName">{lang}wcf.acp.package.name{/lang}</th>
					<th class="columnText columnDomainName">{lang}wcf.acp.application.domainName{/lang}</th>
					<th class="columnText columnDomainPath">{lang}wcf.acp.application.domainPath{/lang}</th>
					<th class="columnText columnCookieDomain">{lang}wcf.acp.application.cookieDomain{/lang}</th>
					<th class="columnText columnCookiePath">{lang}wcf.acp.application.cookiePath{/lang}</th>
					
					{event name='columnHeads'}
				</tr>
			</thead>
			
			<tbody>
				{foreach from=$applicationList item=application}
					<tr>
						<td class="columnIcon"><a href="{link controller='ApplicationEdit' id=$application->packageID}{/link}" class="jsTooltip" title="{lang}wcf.global.button.edit{/lang}"><span class="icon icon16 icon-pencil"></span></a></td>
						<td class="columnID columnPackageID">{#$application->packageID}</td>
						<td class="columnText columnPackageName">
							<a href="{link controller='ApplicationEdit' id=$application->packageID}{/link}">{$application->getPackage()}</a>
							{if $application->isPrimary}
								<aside class="statusDisplay">
									<ul class="statusIcons">
										<li><span class="icon icon16 icon-home jsTooltip" title="{lang}wcf.acp.application.primaryApplication{/lang}"></span></li>
									</ul>
								</aside>
							{/if}
						</td>
						<td class="columnText columnDomainName">{$application->domainName}</td>
						<td class="columnText columnDomainPath">{$application->domainPath}</td>
						<td class="columnText columnCookieDomain">{$application->cookieDomain}</td>
						<td class="columnText columnCookiePath">{$application->cookiePath}</td>
						
						{event name='columns'}
					</tr>
				{/foreach}
			</tbody>
		</table>
	</div>
	
	{hascontent}
		<div class="contentNavigation">
			<nav>
				<ul>
					{content}
						{event name='contentNavigationButtonsBottom'}
					{/content}
				</ul>
			</nav>
		</div>
	{/hascontent}
{/if}

{include file='footer'}
