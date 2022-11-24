		//<![CDATA[
		// js/poshytip/index.js
		$(function(){	
			/*
			$('.tip-ys').poshytip({
				className: 'tip-yellowsimple',
				showTimeout: 1,
				hideTimeout: 0,
				hideAniDuration : 0,
				showAniDuration : 0,
				alignTo: 'target',
				alignX: 'center',
				offsetY: 5,
				allowTipHover: false
			});
			*/
			var personIdCache = {};

			$('.async-personid').poshytip({

				className: 'tip-yellowsimple',
				showTimeout: 1,
				hideTimeout: 0,
				hideAniDuration : 0,
				showAniDuration : 0,
				alignTo: 'target',
				alignX: 'center',
				offsetY: 5,
				refreshAniDuration : 0,
				allowTipHover: false,

				content: function(updateCallback) {

					var rel = $(this).attr('rel');
					
					if (personIdCache[rel] && personIdCache[rel].content){
						return personIdCache[rel].content;
					}

					if (!personIdCache[rel]) {
						personIdCache[rel] = { content: null };
				
						$.getJSON('/ajax/tooltip_person.php?personId=' + escape(rel),
							function(data) {
					
								var container = $('<div/>').addClass('job-box');

									if (data.formattedName){
										$('<p/>').append(data.formattedName).appendTo(container);
									}
									
									if (data.phones){
										
										for (var i = 0; i < data.phones.length; ++i){

											$('<p/>').append(data.phones[i]['phoneNumber'] + ' (' + data.phones[i]['typeName'] + ')').appendTo(container);
											
										}
										
									}
									
									
									//if (data.number){
								//		$('<p/>').append(data.number).appendTo(container);
									//}

								updateCallback(personIdCache[rel].content = container);
							}
						);
					}
					return $(this).attr('tx');;

				}
			});
			
			
			var workOrderIdCache = {};
			
			$('.async-workorderid').poshytip({

				className: 'tip-yellowsimple',
				showTimeout: 1,
				hideTimeout: 0,
				hideAniDuration : 0,
				showAniDuration : 0,
				alignTo: 'target',
				alignX: 'center',
				offsetY: 5,
				refreshAniDuration : 0,
				allowTipHover: false,

				content: function(updateCallback) {

					var rel = $(this).attr('rel');
					
					if (workOrderIdCache[rel] && workOrderIdCache[rel].content){
						return workOrderIdCache[rel].content;
					}

					if (!workOrderIdCache[rel]) {
						workOrderIdCache[rel] = { content: null };
				
						$.getJSON('/ajax/tooltip_workorder.php?workOrderId=' + escape(rel),
							function(data) {
					
								var container = $('<div/>').addClass('job-box');
								    								    
									if (data.name){
									    $('<p/>').append(
									        // data.name // OLD CODE REPLACED 2019-12-04 JM, part of http://bt.dev2.ssseng.com/view.php?id=53
									        data.name.length > 35 ? (data.name.substr(0, 35) + '&hellip;') : data.name // RELACEMENT CODE 2019-12-04 JM  
                                        ).appendTo(container);
									}
									
									
									if (data.jobname){
										$('<p/>').append('<br>Job: ' + 
										    // data.jobname  // OLD CODE REPLACED 2019-12-04 JM, part of http://bt.dev2.ssseng.com/view.php?id=53
										    data.jobname.length > 35 ? (data.jobname.substr(0, 35) + '&hellip;') : data.jobname // RELACEMENT CODE 2019-12-04 JM
                                        ).appendTo(container);
									}
									
									
									/*
									if (data.phones){
										
										for (var i = 0; i < data.phones.length; ++i){

											$('<p/>').append(data.phones[i]['phoneNumber'] + ' (' + data.phones[i]['typeName'] + ')').appendTo(container);
											
										}
										
									}
									*/
									

								updateCallback(workOrderIdCache[rel].content = container);
							}
						);
					}
					return $(this).attr('tx');;

				}
			});
			
			var jobIdCache = {};

			$('.async-jobid').poshytip({

				className: 'tip-yellowsimple',
				showTimeout: 1,
				hideTimeout: 0,
				hideAniDuration : 0,
				showAniDuration : 0,
				alignTo: 'target',
				alignX: 'center',
				offsetY: 5,
				refreshAniDuration : 0,
				allowTipHover: false,

				content: function(updateCallback) {

					var rel = $(this).attr('rel');
					
					if (jobIdCache[rel] && jobIdCache[rel].content){
					    return jobIdCache[rel].content;
					}

					if (!jobIdCache[rel]) {
						jobIdCache[rel] = { content: null };
				
						$.getJSON('/ajax/tooltip_job.php?jobId=' + escape(rel),
							function(data) {
					
								var container = $('<div/>').addClass('job-box');								

									if (data.name){
									    $('<p/>').append(
									        // data.name // OLD CODE REPLACED 2019-12-04 JM, part of http://bt.dev2.ssseng.com/view.php?id=53
									        data.name.length > 35 ? (data.name.substr(0, 35) + '&hellip;') : data.name // RELACEMENT CODE 2019-12-04 JM  
                                        ).appendTo(container);
									}
									if (data.number){
										$('<p/>').append(data.number).appendTo(container);
									}
									if (data.jobStatusName){
										$('<p/>').append(data.jobStatusName).appendTo(container);
									}									
									if (data.description){
                                        $('<p/>').append(
                                            // data.description // OLD CODE REPLACED 2019-12-04 JM, part of http://bt.dev2.ssseng.com/view.php?id=53
                                            data.description.length > 35 ? (data.description.substr(0, 35) + '&hellip;') : data.description // RELACEMENT CODE 2019-12-04 JM  
                                        ).appendTo(container);
                                    }
									// BEGIN Added 2019-11-13 JM
									if (data.ancillaryHTML){
									    $(data.ancillaryHTML).appendTo(container);
									}
									// END Added 2019-11-13 JM

								updateCallback(jobIdCache[rel].content = container);
							}
						);
					}
					return $(this).attr('tx');;

				}
			});
			
			
			$('.async-phoneextension').poshytip({
				className: 'tip-yellowsimple',
				showTimeout: 1,
				hideTimeout: 0,
				hideAniDuration : 0,
				showAniDuration : 0,
				alignTo: 'target',
				alignX: 'center',
				offsetY: 5,
				refreshAniDuration : 0,
				allowTipHover: false,
				content: function(updateCallback) {
					return $(this).attr('tx');;
				}
			});
			
			$('.gets-simple-adjusted-invoice-tooltip').poshytip({
				className: 'tip-yellowsimple',
				showTimeout: 1,
				hideTimeout: 0,
				hideAniDuration : 0,
				showAniDuration : 0,
				alignTo: 'target',
				alignX: 'center',
				offsetY: 5,
				refreshAniDuration : 0,
				allowTipHover: false,
				content: "This includes any adjustments to the invoice"
			});
			
			// location added JM 2019-11-19
			var locationIdCache = {};

			$('.async-locationid').poshytip({

				className: 'tip-yellowsimple',
				showTimeout: 1,
				hideTimeout: 0,
				hideAniDuration : 0,
				showAniDuration : 0,
				alignTo: 'target',
				alignX: 'center',
				offsetY: 5,
				refreshAniDuration : 0,
				allowTipHover: false,

				content: function(updateCallback) {
					var rel = $(this).attr('rel');
					
					if (locationIdCache[rel] && locationIdCache[rel].content){
						return locationIdCache[rel].content;
					}

					if (!locationIdCache[rel]) {
						locationIdCache[rel] = { content: null };
				
						$.getJSON('/ajax/tooltip_location.php?locationId=' + escape(rel),
							function(data) {					
								var container = $('<div/>').addClass('job-box');
                                if (data.formattedAddress){
                                    let display = data.formattedAddress.replace(/\n/gi, '<br />');
                                    $('<p/>').append(display).appendTo(container);
                                }
								updateCallback(locationIdCache[rel].content = container);
							}
						);
					}
					return $(this).attr('tx');
				}
			});
			
			// company-person-header added JM 2019-12-09
			// Where we just have C-P for a header, be able to explain what that means.
			$('.async-company-person-header').poshytip({
				className: 'tip-yellowsimple',
				showTimeout: 1,
				hideTimeout: 0,
				hideAniDuration : 0,
				showAniDuration : 0,
				alignTo: 'target',
				alignX: 'center',
				offsetY: 5,
				refreshAniDuration : 0,
				allowTipHover: false,

				content: function(updateCallback) {
					return "CompanyPerson";
				}
			});
			
		});
		//]]>
