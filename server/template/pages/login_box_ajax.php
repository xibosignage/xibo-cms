<div>
	<h1>Login</h1>
	<p>Xibo requires a valid user login to proceed.</p>
	<form class="dialog_form" method="post" action="index.php?q=login">
		<input type="hidden" name="token" value="<?php echo CreateFormToken() ?>" />
		<div class="login_table">
			<table>
				<tr>
					<td><label for="username">User Name </label></td>
					<td><input class="username" type="text" id="username" name="username" tabindex="1" size="12" /></td>
				</tr>
				<tr>
					<td><label for="password">Password </label></td>
					<td><input class="password" id="password" type="password" name="password" tabindex="2" size="12" /></td>
				</tr>
				<tr>
					<td></td>
					<td>
						<div class="buttons"><button type="submit" class="positive" tabindex="3"><span>Log in</span></button></div>	
					</td>
				</tr>
			</table>
		</div>
	</form>
</div>