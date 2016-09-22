# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.require_version ">= 1.6.0"
VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|
  # Increase our guests RAM to 1GB (docker mysql will fail otherwise)
  config.vm.provider "virtualbox" do |v|
    v.memory = 2048
  end

  config.vm.box = "ubuntu/trusty64"
  config.vm.network :public_network, type: "dhcp"

  # Mount the this folder to /data/web - set group permissions to www-data to give
  # the docker cms-web instance write access
  # NOTE: cms-db instance mounts /data/db inside the guest only. Data persists over vagrant restarts
  # but we don't care about persisting externally if we vagrant destroy.
  config.vm.synced_folder "./", "/data/web", id: "vagrant-web",
    owner: "vagrant",
    group: "www-data",
    mount_options: ["dmode=775,fmode=664"]

  # Provision docker
  config.vm.provision "docker" do |d|
    d.pull_images "mysql:5.6"
    d.pull_images "xibosignage/xibo-cms-dev:latest"
    d.pull_images "xibosignage/xibo-xmr:latest"
    d.run "cms-db",
      image: "mysql:5.6",
      args: "-p 3306:3306 -v /data/db:/var/lib/mysql -e MYSQL_ROOT_PASSWORD=root"
    d.run "cms-xmr",
      image: "xibosignage/xibo-xmr:latest",
      args: "-p 9505:9505"
    d.run "cms-web",
      image: "xibosignage/xibo-cms-dev:latest",
      args: "-p 80:80 -e XIBO_DEV_MODE=true -v /data/web:/var/www/cms -v /data/backup:/var/www/backup --link cms-db:mysql --link cms-xmr:50001"
  end

  # Run a shell provisioner to restart the docker cms-web container (to map the shared folder correctly)
  config.vm.provision "shell",
    inline: "docker restart cms-web",
    run: "always"

  # Install Dependencies
  $script = <<SCRIPT
      echo PHP5-CLI and CURL
      apt-get install -y php5-cli php5-curl
      echo Composer
      wget -q https://getcomposer.org/composer.phar
      mv composer.phar /usr/local/bin/composer
      chmod 755 /usr/local/bin/composer
      cd /data/web && composer install
      echo Provisioning Build System
      echo NodeJs
      curl -sL https://deb.nodesource.com/setup_4.x | sudo -E bash -
      apt-get install -y nodejs
      echo Gulp
      npm install --global gulp-cli
SCRIPT

  config.vm.provision "shell",
    inline: $script

  # Output the IP address for easy access to the VM
  config.vm.provision "shell",
    inline: "/sbin/ifconfig eth1 | grep 'inet addr:' | cut -d: -f2 | awk '{ print $1}'",
    run: "always"
end
