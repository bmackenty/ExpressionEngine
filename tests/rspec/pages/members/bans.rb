class BansMembers < MemberManagerPage

  def load
    main_menu.members_btn.click
    click_link 'Manage Bans'
  end
end