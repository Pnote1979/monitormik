{include file="sections/user-header.tpl"}

					<div class="row">
						<div class="col-md-6">
							<div class="panel panel-default table-condensed">
								<div class="panel-heading">{$_L['Account_Information']}</div>
								<table class="table table-striped table-bordered">
									<tr>
										<td class="small text-success text-uppercase text-normal">{$_L['Username']}</td>
										<td class="small mb15">{$_bill['username']}</td>
									</tr>
									<tr>
										<td class="small text-primary text-uppercase text-normal">{$_L['Plan_Name']}</td>
										<td class="small mb15">{$_bill['namebp']}</td>
									</tr>
									<tr>
										<td class="small text-info text-uppercase text-normal">{$_L['Created_On']}</td>
										<td class="small mb15">{if $_bill['time'] ne ''}{date($_c['date_format'], strtotime($_bill['recharged_on']))} {$_bill['time']}{/if}&nbsp;</td>
									</tr>
									<tr>
										<td class="small text-danger text-uppercase text-normal">{$_L['Expires_On']}</td>
										<td class="small mb15">{if $_bill['time'] ne ''}{date($_c['date_format'], strtotime($_bill['expiration']))} {$_bill['time']}{/if}&nbsp;</td>
									</tr>
								</table>
							</div>
							<br>
							<div class="panel panel-primary panel-hovered panel-stacked mb30">
								<div class="panel-heading">{$_L['Voucher_Activation']}</div>
								<div class="panel-body">
									<form class="form-horizontal" method="post" role="form" action="{$_url}voucher/activation-post" >
										<div class="form-group">
											<label class="col-md-4 control-label">{$_L['Code_Voucher']}</label>
											<div class="col-md-6">
												<input type="text" class="form-control" id="code" name="code" placeholder="{$_L['Enter_Voucher_Code']}">
											</div>
										</div>

										<div class="form-group">
											<div align="center">
												<button class="btn btn-success waves-effect waves-light" type="submit">{$_L['Recharge']}</button>
											</div>
										</div>
									</form>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<br class="visible-xs-inline visible-sm-inline">
							<div class="panel panel-default">
							<div class="panel-heading">{$_L['Announcement']}</div>
							<div class="panel-body" style="height:296px;max-height:296px;overflow:scroll;">
								{include file="$_path/../pages/Announcement.html"}
							</div>
							</div>
						</div>
					</div>

{include file="sections/user-footer.tpl"}
