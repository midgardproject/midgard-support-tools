%define major_version 10.05.99

%define min_glib2 2.24
%define min_libxml2 2.6
%if 0%{?suse_version}
%define devel_requires glib2-devel >= %{min_glib2}, libxml2-devel >= %{min_libxml2}, libgda-4_0-devel, dbus-1-devel, dbus-1-glib-devel, vala, vala-devel
%else
%define devel_requires glib2-devel >= %{min_glib2}, libxml2-devel >= %{min_libxml2}, libgda-devel, dbus-devel, dbus-glib-devel, vala, vala-devel
%endif

%if 0%{?suse_version}
Name:           libmidgardcr3
%else
Name:		midgardcr-core
%endif
Version:        %{major_version}
Release:        OBS
Summary:        Midgard Content Repository core library 

Group:          System Environment/Base
License:        LGPLv2+
URL:            http://www.midgard-project.org/
Source0:        %{url}download/%{name}-%{version}.tar.gz
BuildRoot:      %{_tmppath}/%{name}-%{version}-%{release}-root-%(%{__id_u} -n)

BuildRequires:  pkgconfig >= 0.9.0
BuildRequires:  %{devel_requires}
BuildRequires:  pam-devel

Requires:       glib2 >= %{min_glib2}, libxml2 >= %{min_libxml2}
Requires(post): /bin/ls, /bin/grep

%description                
Midgard is a persistent storage framework built for the replicated
world. It enables developers build applications that have their data in
sync between the desktop, mobile devices and web services. It also
allows for easy sharing of data between users.

Midgard does this all by building on top of technologies like GLib, 
Libgda and D-Bus. It provides developers with object-oriented 
programming interfaces for C, PHP and Python.

This package provides the core C library and tools of the Midgard 
framework. The library allows Midgard applications to access the Midgard 
database using a set of database-independent functions. The library also 
does user authentication and privilege handling.


%package        devel
Summary:        Development files for %{name}
Group:          Development/Libraries
Requires:       %{name} = %{version}-%{release}
Requires:       %{devel_requires}

%description devel
The %{name}-devel package contains libraries and header files for 
developing applications that use %{name}.


%prep
%setup -q


%build
%configure --disable-static
make %{?_smp_mflags}


%install
%if 0%{?suse_version} == 0
rm -rf $RPM_BUILD_ROOT
mkdir -p $(dirname $RPM_BUILD_ROOT)
mkdir $RPM_BUILD_ROOT
%endif
make install DESTDIR=$RPM_BUILD_ROOT
find $RPM_BUILD_ROOT -name '*.la' -exec rm -f {} ';'


%clean
rm -rf $RPM_BUILD_ROOT


%post
/sbin/ldconfig

%postun -p /sbin/ldconfig


%files
%defattr(-,root,root,-)
%doc COPYING
%{_libdir}/*.so.*
%dir %{_sysconfdir}/midgardcr
%dir %{_sysconfdir}/midgardcr/conf.d
%config(noreplace,missingok) %{_sysconfdir}/midgardcr/conf.d/*
%dir %{_datadir}/midgardcr
%{_datadir}/midgardcr/*

%files devel
%defattr(-,root,root,-)
%dir %{_includedir}/midgard
%dir %{_includedir}/midgard/midgardcr
%{_includedir}/midgard/midgardcr.h
%{_includedir}/midgard/midgardcr/*
%{_libdir}/*.so
%{_libdir}/pkgconfig/*


%changelog
* Wed Nov 24 2010 Piotr Pokora <piotrek.pokora@gmail.com> 10.05.99
- New package (based on midgard2-core)
